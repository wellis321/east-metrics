<?php

declare(strict_types=1);

// Daily operational-metrics import: php bin/import-daily.php nec /path/to/export.csv
// Run from a Hostinger cron job once each source system's file drop lands.
// Providers are registered in includes/daily_import.php's DAILY_IMPORT_PROVIDERS.

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/includes/env.php';
load_env(dirname(__DIR__) . '/.env');
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/daily_import.php';

$source = $argv[1] ?? null;
$path = $argv[2] ?? null;

if ($source === null || $path === null || !isset(DAILY_IMPORT_PROVIDERS[$source]) || !is_readable($path)) {
    fwrite(STDERR, 'Usage: php bin/import-daily.php <' . implode('|', array_keys(DAILY_IMPORT_PROVIDERS)) . "> <path-to-export-file>\n");
    exit(1);
}

$provider = DAILY_IMPORT_PROVIDERS[$source];
require_once $provider['require'];

$pdo = db();
$erId = (int) $pdo->query('SELECT id FROM landlords WHERE is_east_renfrewshire = 1 LIMIT 1')->fetchColumn();
if ($erId === 0) {
    fwrite(STDERR, "No East Renfrewshire landlord found — import the SHR data set first.\n");
    exit(1);
}

echo "Parsing $path via $source adapter...\n";
$rows = $provider['parse_fn']($path, $erId);
echo 'Parsed ' . count($rows) . " metric rows.\n";

$result = run_daily_import($rows, strtoupper($source), basename($path), null);

printf("Daily import #%d complete: %d rows.\n", $result['import_id'], $result['row_count']);
