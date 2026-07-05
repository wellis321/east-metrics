<?php

declare(strict_types=1);

// For normalize_numeric() — a generic value-cleaning helper, not SHR-specific.
require_once __DIR__ . '/import.php';

/**
 * Pilot metric set for the daily operational feed — deliberately narrow.
 * Every provider adapter (nec.php, later integra.php/rocc.php/apex.php)
 * normalizes its source rows down to these keys.
 */
const DAILY_METRIC_CATALOG = [
    'repairs_avg_days'  => ['short_label' => 'Average repair completion time', 'unit' => 'days', 'higher_is_better' => false],
    'void_relet_days'   => ['short_label' => 'Average void re-let time', 'unit' => 'days', 'higher_is_better' => false],
    'rent_arrears_pct'  => ['short_label' => 'Current rent arrears', 'unit' => 'percent', 'higher_is_better' => false],
    'asb_open_cases'    => ['short_label' => 'Open ASB cases', 'unit' => 'count', 'higher_is_better' => false],
];

/**
 * Registry of daily-feed source systems — shared by bin/import-daily.php (CLI,
 * for cron) and public/admin/import-daily.php (manual upload). Add a new
 * source (Integra/ROCC/APEX) by writing includes/providers/<key>.php with a
 * parse_<key>_daily_export(string $path, int $landlordId): array function
 * and registering it here.
 */
const DAILY_IMPORT_PROVIDERS = [
    'nec' => [
        'label' => 'NEC',
        'require' => __DIR__ . '/providers/nec.php',
        'parse_fn' => 'parse_nec_daily_export',
    ],
];

/**
 * Recent daily-feed imports (as opposed to annual SHR imports) — identified
 * by having at least one daily_metrics row attached, since the shared
 * imports table doesn't otherwise distinguish the two.
 *
 * @return array<int, array{id:int, filename:string, uploaded_at:string, sources:string, metric_row_count:int}>
 */
function recent_daily_imports(PDO $pdo, int $limit = 10): array
{
    $stmt = $pdo->prepare(
        'SELECT i.id, i.filename, i.uploaded_at, GROUP_CONCAT(DISTINCT dm.source ORDER BY dm.source) AS sources, COUNT(dm.id) AS metric_row_count
           FROM imports i
           JOIN daily_metrics dm ON dm.import_id = i.id
          GROUP BY i.id
          ORDER BY i.uploaded_at DESC
          LIMIT ?'
    );
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function seed_daily_metric_catalog(PDO $pdo): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO daily_metric_catalog (metric_key, short_label, unit, higher_is_better)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE short_label = VALUES(short_label), unit = VALUES(unit), higher_is_better = VALUES(higher_is_better)'
    );

    foreach (DAILY_METRIC_CATALOG as $key => $meta) {
        $stmt->execute([$key, $meta['short_label'], $meta['unit'], (int) $meta['higher_is_better']]);
    }
}

/**
 * Columns a normalized daily row can't be stored without.
 *
 * @return string[] missing required keys, empty if none
 */
function missing_daily_row_keys(array $row): array
{
    $required = ['landlord_id', 'metric_key', 'metric_date', 'value'];

    return array_values(array_diff($required, array_keys($row)));
}

/**
 * Upserts a batch of normalized daily-metric rows (already parsed and mapped
 * by a provider adapter, e.g. providers/nec.php) into daily_metrics, and
 * records an imports row for audit/history alongside the SHR imports.
 *
 * @param array<int, array{landlord_id:int, metric_key:string, metric_date:string, value:string|float|int|null}> $rows
 * @return array{import_id: int, row_count: int}
 */
function run_daily_import(array $rows, string $source, string $filename, ?int $uploadedBy): array
{
    $pdo = db();

    $pdo->beginTransaction();

    try {
        $importStmt = $pdo->prepare(
            'INSERT INTO imports (filename, uploaded_by, row_count) VALUES (?, ?, ?)'
        );
        $importStmt->execute([$filename, $uploadedBy, count($rows)]);
        $importId = (int) $pdo->lastInsertId();

        seed_daily_metric_catalog($pdo);

        $valueStmt = $pdo->prepare(
            'INSERT INTO daily_metrics (landlord_id, metric_key, metric_date, value_numeric, value_text, source, import_id)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE value_numeric = VALUES(value_numeric), value_text = VALUES(value_text),
                 source = VALUES(source), import_id = VALUES(import_id)'
        );

        foreach ($rows as $row) {
            $missing = missing_daily_row_keys($row);
            if ($missing !== []) {
                throw new InvalidArgumentException('Daily metric row missing required key(s): ' . implode(', ', $missing));
            }
            if (!array_key_exists($row['metric_key'], DAILY_METRIC_CATALOG)) {
                throw new InvalidArgumentException("Unknown daily metric key: {$row['metric_key']}");
            }

            $textValue = $row['value'] === null ? null : trim((string) $row['value']);
            $numericValue = normalize_numeric($textValue);

            $valueStmt->execute([
                $row['landlord_id'],
                $row['metric_key'],
                $row['metric_date'],
                $numericValue,
                $textValue,
                $source,
                $importId,
            ]);
        }

        $pdo->commit();

        return ['import_id' => $importId, 'row_count' => count($rows)];
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
