<?php

declare(strict_types=1);

// CLI import: php bin/import.php "documents/Full data set SHR.xlsx"
// Bypasses the web upload form — useful for the initial historical backfill
// and for scripted/local re-imports.

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/includes/env.php';
load_env(dirname(__DIR__) . '/.env');
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/import.php';

$path = $argv[1] ?? null;
if ($path === null || !is_readable($path)) {
    fwrite(STDERR, "Usage: php bin/import.php <path-to-xlsx>\n");
    exit(1);
}

echo "Parsing $path ...\n";
$parsed = parse_shr_xlsx($path);
echo 'Parsed ' . count($parsed['rows']) . " rows, " . count($parsed['headers']) . " columns.\n";

$result = run_shr_import($parsed, basename($path), null);

printf(
    "Import #%d complete: %d rows, %d change events recorded.\n",
    $result['import_id'],
    $result['row_count'],
    $result['change_count']
);
if ($result['prior_column_count'] > 0 && $result['file_column_count'] < $result['prior_column_count']) {
    printf(
        "Note: this file had %d columns, versus %d seen in earlier imports — the missing columns were left unchanged on any existing records, not cleared.\n",
        $result['file_column_count'],
        $result['prior_column_count']
    );
}
