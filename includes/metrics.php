<?php

declare(strict_types=1);

function er_landlord_id(PDO $pdo): ?int
{
    static $id = null;
    if ($id !== null) {
        return $id;
    }
    $row = $pdo->query('SELECT id FROM landlords WHERE is_east_renfrewshire = 1 LIMIT 1')->fetchColumn();
    $id = $row !== false ? (int) $row : 0;

    return $id ?: null;
}

/** @return string[] financial years present in the data, ascending */
function available_financial_years(PDO $pdo): array
{
    return $pdo->query('SELECT DISTINCT financial_year FROM submissions ORDER BY financial_year')->fetchAll(PDO::FETCH_COLUMN);
}

function latest_financial_year(PDO $pdo): ?string
{
    $years = available_financial_years($pdo);

    return $years === [] ? null : end($years);
}

/** @return array<int,array{column_name:string,short_label:string,category:string,unit:string}> */
function key_indicator_catalog(PDO $pdo): array
{
    return $pdo->query(
        "SELECT column_name, short_label, category, unit FROM indicator_catalog
          WHERE is_key = 1 AND unit != 'text'
          ORDER BY category, CAST(column_name AS UNSIGNED), column_name"
    )->fetchAll();
}

/** Key indicators with a known improvement direction — the subset the alerts page can reason about. */
function alertable_indicator_catalog(PDO $pdo): array
{
    return $pdo->query(
        "SELECT column_name, short_label, category, unit, higher_is_better FROM indicator_catalog
          WHERE is_key = 1 AND higher_is_better IS NOT NULL
          ORDER BY category, CAST(column_name AS UNSIGNED), column_name"
    )->fetchAll();
}

function indicator_value_for(PDO $pdo, int $landlordId, string $financialYear, string $column): ?string
{
    $stmt = $pdo->prepare(
        'SELECT iv.value_text FROM indicator_values iv
           JOIN submissions s ON s.id = iv.submission_id
          WHERE s.landlord_id = ? AND s.financial_year = ? AND iv.column_name = ?'
    );
    $stmt->execute([$landlordId, $financialYear, $column]);
    $value = $stmt->fetchColumn();

    return $value === false ? null : $value;
}

/** Scotland-wide average of a numeric indicator for a given year, excluding null/non-numeric values. */
function scotland_average(PDO $pdo, string $financialYear, string $column): ?float
{
    $stmt = $pdo->prepare(
        'SELECT AVG(iv.value_numeric) FROM indicator_values iv
           JOIN submissions s ON s.id = iv.submission_id
          WHERE s.financial_year = ? AND iv.column_name = ? AND iv.value_numeric IS NOT NULL'
    );
    $stmt->execute([$financialYear, $column]);
    $avg = $stmt->fetchColumn();

    return $avg !== null && $avg !== false ? (float) $avg : null;
}

/**
 * A landlord's value for an indicator across every imported financial year.
 *
 * @return array<string,?string> financial_year => value_text
 */
function indicator_series(PDO $pdo, int $landlordId, string $column): array
{
    $stmt = $pdo->prepare(
        'SELECT s.financial_year, iv.value_text FROM submissions s
           LEFT JOIN indicator_values iv ON iv.submission_id = s.id AND iv.column_name = ?
          WHERE s.landlord_id = ?
          ORDER BY s.financial_year'
    );
    $stmt->execute([$column, $landlordId]);

    return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

/** Scotland average of an indicator across every imported financial year. @return array<string,?float> */
function scotland_average_series(PDO $pdo, string $column): array
{
    $years = available_financial_years($pdo);
    $series = [];
    foreach ($years as $year) {
        $series[$year] = scotland_average($pdo, $year, $column);
    }

    return $series;
}

/** @return array<int,array{id:int,name:string}> */
function all_landlords(PDO $pdo): array
{
    return $pdo->query('SELECT id, name FROM landlords ORDER BY name')->fetchAll();
}

function landlord_name(PDO $pdo, int $landlordId): ?string
{
    $stmt = $pdo->prepare('SELECT name FROM landlords WHERE id = ?');
    $stmt->execute([$landlordId]);
    $name = $stmt->fetchColumn();

    return $name === false ? null : $name;
}

/**
 * @param array<int,array{column_name:string,short_label:string,category:string,unit:string}> $catalog
 * @return array<int,array{column_name:string,short_label:string,category:string,unit:string,
 *     current:?string,current_numeric:?float,previous:?string,scotland_avg:?float,delta:?float}>
 */
function dashboard_kpis(PDO $pdo, int $landlordId, string $currentYear, ?string $previousYear): array
{
    $catalog = key_indicator_catalog($pdo);
    $out = [];
    foreach ($catalog as $ind) {
        $current = indicator_value_for($pdo, $landlordId, $currentYear, $ind['column_name']);
        $previous = $previousYear !== null ? indicator_value_for($pdo, $landlordId, $previousYear, $ind['column_name']) : null;
        $scotlandAvg = scotland_average($pdo, $currentYear, $ind['column_name']);

        $currentNumeric = is_numeric($current) ? (float) $current : null;
        $previousNumeric = is_numeric($previous) ? (float) $previous : null;
        $delta = ($currentNumeric !== null && $previousNumeric !== null) ? $currentNumeric - $previousNumeric : null;

        $out[] = [
            'column_name' => $ind['column_name'],
            'short_label' => $ind['short_label'],
            'category' => $ind['category'],
            'unit' => $ind['unit'],
            'current' => $current,
            'current_numeric' => $currentNumeric,
            'previous' => $previous,
            'scotland_avg' => $scotlandAvg,
            'delta' => $delta,
        ];
    }

    return $out;
}

/** @return array<string,array<int,array{column_name:string,short_label:string,category:string,unit:string,current:?string,current_numeric:?float,previous:?string,scotland_avg:?float,delta:?float}>> grouped by category */
function group_kpis_by_category(array $kpis): array
{
    $grouped = [];
    foreach ($kpis as $kpi) {
        $grouped[$kpi['category']][] = $kpi;
    }

    return $grouped;
}

/**
 * Flags indicators that need attention: a declining year-on-year trend,
 * currently below the Scotland average, or still above average but closing
 * in on it faster than a rounding blip would explain. Only indicators with
 * a known improvement direction (see indicator_higher_is_better()) are
 * considered — direction-less absolute totals are left out entirely.
 *
 * @return array<int,array{
 *   column_name:string,short_label:string,category:string,unit:string,higher_is_better:bool,
 *   current:?float,previous:?float,scotland_avg:?float,previous_scotland_avg:?float,
 *   delta:?float,gap:?float,previous_gap:?float,
 *   is_declining:bool,is_below_average:bool,is_approaching_average:bool
 * }>
 */
function dashboard_alerts(PDO $pdo, int $landlordId, string $currentYear, ?string $previousYear): array
{
    $epsilon = 0.05;

    $catalog = alertable_indicator_catalog($pdo);
    $alerts = [];

    foreach ($catalog as $ind) {
        $higherIsBetter = (bool) $ind['higher_is_better'];
        $column = $ind['column_name'];

        $current = indicator_value_for($pdo, $landlordId, $currentYear, $column);
        $current = is_numeric($current) ? (float) $current : null;
        $previous = $previousYear !== null ? indicator_value_for($pdo, $landlordId, $previousYear, $column) : null;
        $previous = is_numeric($previous) ? (float) $previous : null;

        $currentAvg = scotland_average($pdo, $currentYear, $column);
        $previousAvg = $previousYear !== null ? scotland_average($pdo, $previousYear, $column) : null;

        $delta = ($current !== null && $previous !== null) ? $current - $previous : null;
        $isDeclining = $delta !== null && ($higherIsBetter ? $delta < -$epsilon : $delta > $epsilon);

        $isBelowAverage = $current !== null && $currentAvg !== null
            && ($higherIsBetter ? $current < $currentAvg - $epsilon : $current > $currentAvg + $epsilon);

        $gap = ($current !== null && $currentAvg !== null)
            ? ($higherIsBetter ? $current - $currentAvg : $currentAvg - $current)
            : null;
        $previousGap = ($previous !== null && $previousAvg !== null)
            ? ($higherIsBetter ? $previous - $previousAvg : $previousAvg - $previous)
            : null;

        $isApproachingAverage = !$isBelowAverage && $gap !== null && $gap > 0
            && $previousGap !== null && $gap < $previousGap - $epsilon;

        if (!$isDeclining && !$isBelowAverage && !$isApproachingAverage) {
            continue;
        }

        $alerts[] = [
            'column_name' => $column,
            'short_label' => $ind['short_label'],
            'category' => $ind['category'],
            'unit' => $ind['unit'],
            'higher_is_better' => $higherIsBetter,
            'current' => $current,
            'previous' => $previous,
            'scotland_avg' => $currentAvg,
            'previous_scotland_avg' => $previousAvg,
            'delta' => $delta,
            'gap' => $gap,
            'previous_gap' => $previousGap,
            'is_declining' => $isDeclining,
            'is_below_average' => $isBelowAverage,
            'is_approaching_average' => $isApproachingAverage,
        ];
    }

    usort($alerts, static function (array $a, array $b): int {
        $weight = static fn (array $x) => ($x['is_below_average'] ? 100 : 0) + ($x['is_approaching_average'] ? 10 : 0) + ($x['is_declining'] ? 1 : 0);

        return $weight($b) <=> $weight($a);
    });

    return $alerts;
}

/**
 * The indicator columns to include in an export: either the curated ~40 key
 * indicators shown throughout the dashboard, or every raw column the
 * regulator's file has ever contained (identity fields — name, financial
 * year, etc — are added separately by export_rows() and excluded here).
 *
 * @return string[]
 */
function export_columns(PDO $pdo, bool $keyOnly): array
{
    if ($keyOnly) {
        return array_column(key_indicator_catalog($pdo), 'column_name');
    }

    return $pdo->query('SELECT DISTINCT column_name FROM indicator_values ORDER BY column_name')->fetchAll(PDO::FETCH_COLUMN);
}

/** @return int total landlord+year rows an export with these filters would contain */
function export_row_count(PDO $pdo, ?string $year, bool $erOnly): int
{
    $where = [];
    $params = [];
    if ($year !== null) {
        $where[] = 's.financial_year = ?';
        $params[] = $year;
    }
    if ($erOnly) {
        $where[] = 'l.is_east_renfrewshire = 1';
    }
    $whereSql = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM submissions s JOIN landlords l ON l.id = s.landlord_id $whereSql");
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

/**
 * Reconstructs one wide row per landlord+year (identity fields followed by
 * the given indicator columns), the same shape as the original SHR file,
 * from the EAV-style indicator_values table. Pass $limit to cap the number
 * of landlord+year rows fetched, e.g. for an on-page preview.
 *
 * @param string[] $columns
 * @return array<int,array<string,mixed>>
 */
function export_rows(PDO $pdo, ?string $year, bool $erOnly, array $columns, ?int $limit = null): array
{
    $where = [];
    $params = [];
    if ($year !== null) {
        $where[] = 's.financial_year = ?';
        $params[] = $year;
    }
    if ($erOnly) {
        $where[] = 'l.is_east_renfrewshire = 1';
    }
    $whereSql = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';
    $limitSql = $limit !== null ? 'LIMIT ' . (int) $limit : '';

    $stmt = $pdo->prepare(
        "SELECT s.id, l.name, l.social_landlord_id, l.landlord_type, l.settlement, l.national_operator, s.financial_year
           FROM submissions s JOIN landlords l ON l.id = s.landlord_id
           $whereSql
           ORDER BY l.name, s.financial_year
           $limitSql"
    );
    $stmt->execute($params);
    $submissions = $stmt->fetchAll();

    $rows = [];
    foreach ($submissions as $sub) {
        $values = fetch_indicator_values($pdo, (int) $sub['id']);
        $row = [
            'Landlord name' => $sub['name'],
            'Social Landlord ID' => $sub['social_landlord_id'],
            'Landlord type' => $sub['landlord_type'],
            'Settlement' => $sub['settlement'],
            'National operator' => $sub['national_operator'],
            'Financial year' => $sub['financial_year'],
        ];
        foreach ($columns as $column) {
            $row[$column] = $values[$column] ?? null;
        }
        $rows[] = $row;
    }

    return $rows;
}
