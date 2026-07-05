<?php

declare(strict_types=1);

/**
 * NEC daily export adapter — parses NEC's daily flat file into the row shape
 * run_daily_import() expects: [landlord_id, metric_key, metric_date, value].
 *
 * PLACEHOLDER: the column names below are stand-ins. NEC's actual daily
 * export field names are not yet known — this must be updated against a
 * real sample file/export before use, or every row will fail the "unknown
 * NEC column" check below rather than silently importing wrong data.
 */
const NEC_COLUMN_MAP = [
    'TODO_NEC_REPAIRS_AVG_DAYS' => 'repairs_avg_days',
    'TODO_NEC_VOID_RELET_DAYS'  => 'void_relet_days',
    'TODO_NEC_RENT_ARREARS_PCT' => 'rent_arrears_pct',
    'TODO_NEC_ASB_OPEN_CASES'   => 'asb_open_cases',
];

/**
 * @return array<int, array{landlord_id:int, metric_key:string, metric_date:string, value:string|null}>
 */
function parse_nec_daily_export(string $path, int $landlordId): array
{
    $handle = fopen($path, 'r');
    if ($handle === false) {
        throw new InvalidArgumentException("Could not open NEC export file: $path");
    }

    $headers = fgetcsv($handle, null, ',', '"', '\\');
    if ($headers === false) {
        fclose($handle);
        throw new InvalidArgumentException('NEC export file is empty.');
    }
    $headers = array_map(static fn ($h) => trim((string) $h), $headers);

    $dateColumn = 'TODO_NEC_METRIC_DATE';
    if (!in_array($dateColumn, $headers, true)) {
        fclose($handle);
        throw new InvalidArgumentException(
            "NEC column mapping is still a placeholder — update includes/providers/nec.php's "
            . 'NEC_COLUMN_MAP and $dateColumn against a real NEC export before importing.'
        );
    }

    $rows = [];
    while (($cells = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
        $assoc = array_combine($headers, $cells);
        if ($assoc === false) {
            continue; // column count mismatch on this line — skip rather than guess
        }

        $metricDate = trim((string) ($assoc[$dateColumn] ?? ''));
        if ($metricDate === '') {
            continue;
        }

        foreach (NEC_COLUMN_MAP as $necColumn => $metricKey) {
            if (!array_key_exists($necColumn, $assoc)) {
                continue;
            }
            $rows[] = [
                'landlord_id' => $landlordId,
                'metric_key' => $metricKey,
                'metric_date' => $metricDate,
                'value' => $assoc[$necColumn],
            ];
        }
    }

    fclose($handle);

    return $rows;
}
