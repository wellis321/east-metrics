<?php

declare(strict_types=1);

use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Columns captured directly on the landlords/submissions tables rather than
 * as indicator_values rows — stored once per landlord (or landlord+year),
 * not duplicated across every column-value pair.
 */
const IDENTITY_COLUMNS = ['Social Landlord ID', 'Landlord name', 'Financial year', 'Landlord type', 'Settlement', 'National operator'];

/**
 * Parses the SHR "Full data set" xlsx into an array of associative rows
 * (header => cell value), reading only the first worksheet.
 *
 * @return array{headers: string[], rows: array<int, array<string, mixed>>}
 */
function parse_shr_xlsx(string $path): array
{
    $spreadsheet = IOFactory::load($path);
    $sheet = $spreadsheet->getActiveSheet();
    $iterator = $sheet->getRowIterator();

    $headers = [];
    $rows = [];
    $rowNum = 0;

    foreach ($iterator as $row) {
        $rowNum++;
        $cells = [];
        $cellIterator = $row->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(false);
        foreach ($cellIterator as $cell) {
            $cells[] = $cell->getFormattedValue();
        }

        if ($rowNum === 1) {
            $headers = array_map(static fn ($v) => trim((string) $v), $cells);
            continue;
        }

        if (count(array_filter($cells, static fn ($v) => trim((string) $v) !== '')) === 0) {
            continue; // skip blank rows
        }

        $assoc = [];
        foreach ($headers as $i => $header) {
            if ($header === '') {
                continue;
            }
            $assoc[$header] = $cells[$i] ?? null;
        }
        $rows[] = $assoc;
    }

    return ['headers' => $headers, 'rows' => $rows];
}

/**
 * A raw SHR column is one of the regulator's own pre-computed summary
 * indicators (e.g. "6 - Percentage properties meeting SHQS year end",
 * "3 & 4 - Percentage of all complaints responded to in full Stage 1")
 * rather than a raw input field, if its header starts with one or more
 * numbers followed by " - ".
 */
function is_key_indicator_column(string $column): bool
{
    return (bool) preg_match('/^\d+(\s*&\s*\d+)?\s*-\s*/', $column);
}

function guess_indicator_unit(string $column): string
{
    $lower = strtolower($column);
    if (str_contains($lower, 'percentage') || str_contains($lower, '%')) {
        return 'percent';
    }
    if (str_contains($lower, 'cost') || str_contains($lower, '£') || str_contains($lower, 'rent due') || str_contains($lower, 'rent collected') || str_contains($lower, 'arrears')) {
        return 'gbp';
    }
    if (str_contains($lower, 'hours')) {
        return 'hours';
    }
    if (str_contains($lower, 'days') || str_contains($lower, 'time to complete') || str_contains($lower, 'average time')) {
        return 'days';
    }
    if (str_contains($lower, 'average') || str_contains($lower, 'number') || str_contains($lower, 'total')) {
        return 'count';
    }

    return 'text';
}

/**
 * Whether a higher value is the better outcome for a key indicator — used to
 * detect declining trends and below-average performance on the alerts page.
 * Days/hours indicators are always "lower is better". Percent indicators
 * are looked up from a curated list of the regulator's known Charter
 * indicators, since the direction isn't inferable from the number alone
 * (e.g. "18 - rent lost to void properties" is bad when high, "26 - rent
 * collected" is good when high). Indicators not in the list (including
 * absolute £/count totals that aren't comparable across landlord sizes,
 * and any new indicator a future SHR file might introduce) return null and
 * are left out of the alerts page rather than guessed at.
 */
function indicator_higher_is_better(string $column, string $unit): ?bool
{
    if (in_array($unit, ['days', 'hours'], true)) {
        return false;
    }
    if ($unit !== 'percent') {
        return null;
    }

    static $lowerIsBetter = [
        '18 - Percentage of rent due lost through empty properties',
        '22 - Percentage of court actions initiated resulted in eviction',
        '22 - Percentage of court actions initiated resulted in eviction for anti-social behaviour',
        '22 - Percentage of court actions initiated resulted in eviction for rent not paid',
        '22 - Percentage of court actions initiated which resulted in eviction for other reasons',
        '14 - Percentage tenancy offers refused',
        '17 - Percentage lettable self-contained houses that became vacant in year',
        '27 - Percentage gross rent arrears of rent due',
    ];
    static $skip = [
        // Volume/pathway rates, not a quality judgement in either direction.
        '23 - Percentage of Section 5 and other  referrals for homeless households by LA result in offer',
        '24 - Percentage of homeless households referred to RSLs under Section 5 and other referral routes',
    ];

    if (in_array($column, $lowerIsBetter, true)) {
        return false;
    }
    if (in_array($column, $skip, true)) {
        return null;
    }

    // Every other percent indicator in the Charter set (satisfaction,
    // SHQS/quality compliance, complaints responded to in full, rent
    // collected, tenancies sustained, offers let, ASB resolved, etc.) is
    // higher-is-better.
    return true;
}

function guess_indicator_category(string $column): string
{
    $n = (int) $column;
    return match (true) {
        $n >= 1 && $n <= 5   => 'Tenant satisfaction',
        $n >= 6 && $n <= 13  => 'Housing quality & repairs',
        $n >= 14 && $n <= 17 => 'Neighbourhood & lettings',
        $n >= 18 && $n <= 24 => 'Access to housing & support',
        $n >= 25 && $n <= 30 => 'Value for money & rents',
        $n >= 31 && $n <= 32 => 'Gypsy/Traveller sites',
        default              => 'Other',
    };
}

function normalize_numeric(?string $value): ?float
{
    if ($value === null || $value === '') {
        return null;
    }
    $clean = str_replace(['%', '£', ',', ' '], '', $value);
    if (!is_numeric($clean)) {
        return null;
    }

    return (float) $clean;
}

/**
 * Imports a parsed SHR dataset: upserts landlords, records an import batch,
 * writes submissions + indicator_values, seeds the indicator catalog, and
 * generates change_events by diffing against the previous import.
 *
 * @param array{headers: string[], rows: array<int, array<string, mixed>>} $parsed
 * @return array{import_id: int, row_count: int, change_count: int}
 */
/**
 * Columns a row can't be attributed or diffed without. Everything else is
 * optional — a file with only some indicator columns is accepted, and
 * columns it omits are simply left untouched on existing records (see the
 * upsert in run_shr_import()) rather than wiped.
 *
 * @return string[] missing required column names, empty if none
 */
function missing_required_headers(array $headers): array
{
    $required = ['Landlord name', 'Financial year'];

    return array_values(array_diff($required, $headers));
}

function run_shr_import(array $parsed, string $filename, ?int $uploadedBy): array
{
    $missing = missing_required_headers($parsed['headers']);
    if ($missing !== []) {
        throw new InvalidArgumentException(
            'This file is missing required column(s): ' . implode(', ', $missing) . '. Nothing was imported.'
        );
    }

    $pdo = db();

    // For the "this file looks thin" note below — how many distinct columns
    // has the database seen before now, across any prior import.
    $priorColumnCount = (int) $pdo->query('SELECT COUNT(DISTINCT column_name) FROM indicator_values')->fetchColumn();
    $fileColumnCount = count(array_diff($parsed['headers'], IDENTITY_COLUMNS, ['']));

    $pdo->beginTransaction();

    try {
        $years = [];
        foreach ($parsed['rows'] as $row) {
            $fy = trim((string) ($row['Financial year'] ?? ''));
            if ($fy !== '') {
                $years[$fy] = true;
            }
        }

        $importStmt = $pdo->prepare(
            'INSERT INTO imports (filename, uploaded_by, row_count, financial_years) VALUES (?, ?, ?, ?)'
        );
        $importStmt->execute([$filename, $uploadedBy, count($parsed['rows']), implode(', ', array_keys($years))]);
        $importId = (int) $pdo->lastInsertId();

        seed_indicator_catalog($pdo, $parsed['headers']);

        // Snapshot pre-import state before writing anything, so that loading
        // several years of history in one go doesn't get diffed against rows
        // this same import just inserted moments earlier — only a landlord's
        // true latest pre-existing year counts as the "previous" year.
        // Keyed on landlord_id (not the name string) — a handful of source
        // rows vary the case of the landlord name (e.g. "lanarkshire..." vs
        // "Lanarkshire..."), and while MySQL's ci collation correctly treats
        // those as the same landlord row, a PHP array keyed by the raw
        // string would not, causing false "new landlord" misses.
        $preImportYears = []; // landlord_id => [financial_year => submission_id]
        $snapshotStmt = $pdo->query(
            'SELECT s.landlord_id, s.financial_year, s.id AS submission_id FROM submissions s'
        );
        foreach ($snapshotStmt->fetchAll() as $r) {
            $preImportYears[(int) $r['landlord_id']][$r['financial_year']] = (int) $r['submission_id'];
        }
        $maxYearValuesCache = []; // submission_id => values map, filled lazily

        // Keyed on name, not "Social Landlord ID" — the regulator reissued
        // that ID (new UUID scheme) starting with the 2020/2021 return, so
        // it is not a stable identifier across this file's 6 years.
        $landlordStmt = $pdo->prepare(
            'INSERT INTO landlords (name, social_landlord_id, landlord_type, settlement, national_operator, is_east_renfrewshire)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE social_landlord_id = VALUES(social_landlord_id), landlord_type = VALUES(landlord_type),
                 settlement = VALUES(settlement), national_operator = VALUES(national_operator),
                 is_east_renfrewshire = VALUES(is_east_renfrewshire)'
        );
        $landlordIdStmt = $pdo->prepare('SELECT id FROM landlords WHERE name = ?');

        $insertSubmissionStmt = $pdo->prepare(
            'INSERT INTO submissions (import_id, first_import_id, landlord_id, financial_year) VALUES (?, ?, ?, ?)'
        );

        $changeCount = 0;

        foreach ($parsed['rows'] as $row) {
            $slid = trim((string) ($row['Social Landlord ID'] ?? ''));
            $name = trim((string) ($row['Landlord name'] ?? ''));
            $fy = trim((string) ($row['Financial year'] ?? ''));
            if ($name === '' || $fy === '') {
                continue;
            }

            $isEr = str_contains(strtolower($name), 'east renfrewshire') ? 1 : 0;

            $landlordStmt->execute([
                $name,
                $slid !== '' ? $slid : null,
                $row['Landlord type'] ?? null,
                $row['Settlement'] ?? null,
                $row['National operator'] ?? null,
                $isEr,
            ]);
            $landlordIdStmt->execute([$name]);
            $landlordId = (int) $landlordIdStmt->fetchColumn();

            $priorYearsForLandlord = $preImportYears[$landlordId] ?? [];
            $isBrandNewLandlord = $priorYearsForLandlord === [];
            $existingSubmissionId = $priorYearsForLandlord[$fy] ?? null; // revision of an already-imported year

            $priorValues = $existingSubmissionId !== null
                ? fetch_indicator_values($pdo, $existingSubmissionId)
                : [];

            $priorYearValues = [];
            if (!$isBrandNewLandlord && $existingSubmissionId === null) {
                // Genuinely new year for an established landlord — diff against
                // their latest pre-existing year, not one inserted this import.
                $maxPriorYear = max(array_keys($priorYearsForLandlord));
                $maxPriorSubmissionId = $priorYearsForLandlord[$maxPriorYear];
                if (!array_key_exists($maxPriorSubmissionId, $maxYearValuesCache)) {
                    $maxYearValuesCache[$maxPriorSubmissionId] = fetch_indicator_values($pdo, $maxPriorSubmissionId);
                }
                $priorYearValues = $maxYearValuesCache[$maxPriorSubmissionId];
            }

            if ($existingSubmissionId !== null) {
                $submissionId = $existingSubmissionId;
                $pdo->prepare('UPDATE submissions SET import_id = ? WHERE id = ?')->execute([$importId, $submissionId]);
            } else {
                $insertSubmissionStmt->execute([$importId, $importId, $landlordId, $fy]);
                $submissionId = (int) $pdo->lastInsertId();
            }

            // Upsert per column rather than delete-then-reinsert: a file
            // that only carries a subset of columns (a partial extract, or
            // an older/newer SHR template with a different field set) must
            // not wipe out previously-recorded values for columns it simply
            // doesn't include.
            $valueStmt = $pdo->prepare(
                'INSERT INTO indicator_values (submission_id, column_name, value_text, value_numeric) VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE value_text = VALUES(value_text), value_numeric = VALUES(value_numeric)'
            );

            foreach ($row as $column => $value) {
                if (in_array($column, IDENTITY_COLUMNS, true)) {
                    continue;
                }
                $textValue = $value === null ? null : trim((string) $value);
                if ($textValue === '') {
                    $textValue = null;
                }
                $numericValue = normalize_numeric($textValue);
                $valueStmt->execute([$submissionId, $column, $textValue, $numericValue]);

                if (!is_key_indicator_column($column)) {
                    continue;
                }

                if ($isBrandNewLandlord) {
                    continue; // one new_landlord event only, added below
                }

                if (array_key_exists($column, $priorValues) && $priorValues[$column] !== $textValue) {
                    record_change_event($pdo, $importId, $landlordId, $fy, $column, 'revised_prior_year', $priorValues[$column], $textValue);
                    $changeCount++;
                } elseif (!array_key_exists($column, $priorValues) && array_key_exists($column, $priorYearValues) && $priorYearValues[$column] !== $textValue) {
                    record_change_event($pdo, $importId, $landlordId, $fy, $column, 'new_year_data', $priorYearValues[$column], $textValue);
                    $changeCount++;
                }
            }

            if ($isBrandNewLandlord) {
                record_change_event($pdo, $importId, $landlordId, $fy, 'Landlord name', 'new_landlord', null, $name);
                $changeCount++;
            }
        }

        $pdo->commit();

        return [
            'import_id' => $importId,
            'row_count' => count($parsed['rows']),
            'change_count' => $changeCount,
            'file_column_count' => $fileColumnCount,
            'prior_column_count' => $priorColumnCount,
        ];
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** @return array<string,?string> column_name => value_text */
function fetch_indicator_values(PDO $pdo, int $submissionId): array
{
    $stmt = $pdo->prepare('SELECT column_name, value_text FROM indicator_values WHERE submission_id = ?');
    $stmt->execute([$submissionId]);

    return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

function record_change_event(
    PDO $pdo,
    int $importId,
    int $landlordId,
    string $financialYear,
    string $columnName,
    string $changeType,
    ?string $previousValue,
    ?string $newValue
): void {
    $pctChange = null;
    $prevNum = normalize_numeric($previousValue);
    $newNum = normalize_numeric($newValue);
    if ($prevNum !== null && $newNum !== null && $prevNum != 0.0) {
        $pctChange = round((($newNum - $prevNum) / abs($prevNum)) * 100, 2);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO change_events (import_id, landlord_id, financial_year, column_name, change_type, previous_value, new_value, pct_change)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$importId, $landlordId, $financialYear, $columnName, $changeType, $previousValue, $newValue, $pctChange]);
}

/** @param string[] $headers */
function seed_indicator_catalog(PDO $pdo, array $headers): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO indicator_catalog (column_name, short_label, category, unit, is_key, higher_is_better)
         VALUES (?, ?, ?, ?, 1, ?)
         ON DUPLICATE KEY UPDATE short_label = VALUES(short_label), category = VALUES(category), unit = VALUES(unit),
             is_key = 1, higher_is_better = VALUES(higher_is_better)'
    );

    foreach ($headers as $column) {
        if ($column === '' || !is_key_indicator_column($column)) {
            continue;
        }
        $shortLabel = preg_replace('/^\d+(\s*&\s*\d+)?\s*-\s*/', '', $column) ?? $column;
        $unit = guess_indicator_unit($column);
        $higherIsBetter = indicator_higher_is_better($column, $unit);
        $stmt->execute([
            $column,
            $shortLabel,
            guess_indicator_category($column),
            $unit,
            // PDO's emulated prepares cast PHP false to '' rather than 0,
            // which MySQL rejects for a TINYINT column under strict mode.
            $higherIsBetter === null ? null : (int) $higherIsBetter,
        ]);
    }
}

/**
 * A landlord+year is only safe to remove entirely if this import created it
 * from scratch (submissions.first_import_id = this import — set once, at
 * INSERT, and never updated). If this import instead *revised* an
 * already-existing landlord+year, indicator_values only holds the new
 * figures (a revision overwrites all ~500 raw columns, not just the ~40 key
 * indicators that get a change_events row) — so there's no full-fidelity
 * history to restore. Re-importing a landlord+year with unchanged values
 * still re-stamps submissions.import_id (see run_shr_import()), which is
 * why this can't simply be inferred from change_events.
 *
 * @return array{filename:string,uploaded_at:string,row_count:int,removable_count:int,revised_count:int,change_event_count:int}|null
 */
function import_deletion_summary(PDO $pdo, int $importId): ?array
{
    $importStmt = $pdo->prepare('SELECT * FROM imports WHERE id = ?');
    $importStmt->execute([$importId]);
    $import = $importStmt->fetch();
    if ($import === false) {
        return null;
    }

    $freshStmt = $pdo->prepare('SELECT COUNT(*) FROM submissions WHERE import_id = ? AND first_import_id = ?');
    $freshStmt->execute([$importId, $importId]);

    $revisedStmt = $pdo->prepare(
        'SELECT COUNT(*) FROM submissions WHERE import_id = ? AND (first_import_id IS NULL OR first_import_id != ?)'
    );
    $revisedStmt->execute([$importId, $importId]);

    $changeEventStmt = $pdo->prepare('SELECT COUNT(*) FROM change_events WHERE import_id = ?');
    $changeEventStmt->execute([$importId]);

    return [
        'filename' => (string) $import['filename'],
        'uploaded_at' => (string) $import['uploaded_at'],
        'row_count' => (int) $import['row_count'],
        'removable_count' => (int) $freshStmt->fetchColumn(),
        'revised_count' => (int) $revisedStmt->fetchColumn(),
        'change_event_count' => (int) $changeEventStmt->fetchColumn(),
    ];
}

/**
 * Deletes an import. Landlord/year records created fresh by this import
 * (first_import_id = this import) are removed completely (submission +
 * indicator_values, cascading). Records that pre-existed and were merely
 * revised by this import are left as-is — their values are not reverted —
 * but are detached from this import (import_id set to NULL) so it can be
 * deleted; re-upload the correct file afterwards to fix them. Rows this
 * import created but a later import has since revised are untouched, only
 * their now-dangling first_import_id is cleared. Landlords left with zero
 * submissions afterward are removed too.
 *
 * @return array{removed_submissions:int,unattributed_submissions:int,removed_landlords:int}
 */
function delete_import(PDO $pdo, int $importId): array
{
    $pdo->beginTransaction();

    try {
        $freshStmt = $pdo->prepare('SELECT id, landlord_id FROM submissions WHERE import_id = ? AND first_import_id = ?');
        $freshStmt->execute([$importId, $importId]);
        $freshRows = $freshStmt->fetchAll();

        $revisedStmt = $pdo->prepare(
            'SELECT id, landlord_id FROM submissions WHERE import_id = ? AND (first_import_id IS NULL OR first_import_id != ?)'
        );
        $revisedStmt->execute([$importId, $importId]);
        $revisedRows = $revisedStmt->fetchAll();

        $touchedLandlordIds = [];

        foreach ($freshRows as $r) {
            $touchedLandlordIds[(int) $r['landlord_id']] = true;
            $pdo->prepare('DELETE FROM submissions WHERE id = ?')->execute([(int) $r['id']]);
        }
        foreach ($revisedRows as $r) {
            $touchedLandlordIds[(int) $r['landlord_id']] = true;
            $pdo->prepare('UPDATE submissions SET import_id = NULL WHERE id = ?')->execute([(int) $r['id']]);
        }

        // Rows this import created but a later import has since revised —
        // data untouched (that later import owns it now), just clear the
        // reference to this import so it can be deleted.
        $pdo->prepare('UPDATE submissions SET first_import_id = NULL WHERE first_import_id = ? AND import_id != ?')
            ->execute([$importId, $importId]);

        $pdo->prepare('DELETE FROM change_events WHERE import_id = ?')->execute([$importId]);
        $pdo->prepare('DELETE FROM imports WHERE id = ?')->execute([$importId]);

        $removedLandlords = 0;
        foreach (array_keys($touchedLandlordIds) as $landlordId) {
            $countStmt = $pdo->prepare('SELECT COUNT(*) FROM submissions WHERE landlord_id = ?');
            $countStmt->execute([$landlordId]);
            if ((int) $countStmt->fetchColumn() === 0) {
                $pdo->prepare('DELETE FROM landlords WHERE id = ?')->execute([$landlordId]);
                $removedLandlords++;
            }
        }

        $pdo->commit();

        return [
            'removed_submissions' => count($freshRows),
            'unattributed_submissions' => count($revisedRows),
            'removed_landlords' => $removedLandlords,
        ];
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
