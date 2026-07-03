<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/metrics.php';
require_once dirname(__DIR__) . '/includes/import.php';

require_login();

$pdo = db();
$years = available_financial_years($pdo);

$format = $_GET['format'] ?? '';
$year = $_GET['year'] ?? '';
$year = in_array($year, $years, true) ? $year : null;
$scope = ($_GET['scope'] ?? 'key') === 'all' ? 'all' : 'key';
$landlordScope = ($_GET['landlords'] ?? 'all') === 'er' ? 'er' : 'all';
$showPreview = ($_GET['preview'] ?? '') === '1';
$previewLimit = 25;

if (in_array($format, ['csv', 'json', 'xlsx'], true) && $years !== []) {
    $columns = export_columns($pdo, $scope === 'key');
    $rows = export_rows($pdo, $year, $landlordScope === 'er', $columns);

    $filename = implode('-', array_filter([
        'shr-export',
        $landlordScope === 'er' ? 'east-renfrewshire' : 'all-landlords',
        $year !== null ? str_replace('/', '_', $year) : 'all-years',
        $scope === 'key' ? 'key-indicators' : 'full',
    ]));

    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        $out = fopen('php://output', 'w');
        if ($rows !== []) {
            fputcsv($out, array_keys($rows[0]));
            foreach ($rows as $row) {
                fputcsv($out, array_values($row));
            }
        }
        fclose($out);
        exit;
    }

    if ($format === 'json') {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.json"');
        echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        exit;
    }

    if ($format === 'xlsx') {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        if ($rows !== []) {
            $sheet->fromArray(array_keys($rows[0]), null, 'A1');
            $sheet->fromArray(array_map('array_values', $rows), null, 'A2');
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}

$previewColumns = [];
$previewRows = [];
$previewTotal = 0;
if ($showPreview && $years !== []) {
    $previewColumns = export_columns($pdo, $scope === 'key');
    $previewTotal = export_row_count($pdo, $year, $landlordScope === 'er');
    $previewRows = export_rows($pdo, $year, $landlordScope === 'er', $previewColumns, $previewLimit);
}

ob_start();
?>
<h1>Export data</h1>
<p class="subtitle">Download the stored SHR Charter data for use elsewhere — spreadsheets, reporting, or another system.</p>

<?php if ($years === []): ?>
    <p class="empty-state">No data has been imported yet.</p>
<?php else: ?>
<div class="card" style="max-width:520px;">
    <form method="GET">
        <div class="form-row">
            <label for="scope">What to include</label>
            <select id="scope" name="scope">
                <option value="key" <?= $scope === 'key' ? 'selected' : '' ?>>Key indicators only (~40 headline metrics)</option>
                <option value="all" <?= $scope === 'all' ? 'selected' : '' ?>>All raw data (every field from the regulator's file)</option>
            </select>
        </div>
        <div class="form-row">
            <label for="landlords">Landlords</label>
            <select id="landlords" name="landlords">
                <option value="all" <?= $landlordScope === 'all' ? 'selected' : '' ?>>All landlords (Scotland-wide)</option>
                <option value="er" <?= $landlordScope === 'er' ? 'selected' : '' ?>>East Renfrewshire only</option>
            </select>
        </div>
        <div class="form-row">
            <label for="year">Financial year</label>
            <select id="year" name="year">
                <option value="">All years</option>
                <?php foreach (array_reverse($years) as $y): ?>
                    <option value="<?= h($y) ?>" <?= $y === $year ? 'selected' : '' ?>><?= h($y) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="display:flex;gap:.6rem;flex-wrap:wrap;margin-top:1.25rem;">
            <button type="submit" class="btn btn-secondary" name="preview" value="1">Preview</button>
            <button type="submit" class="btn" name="format" value="csv">Download CSV</button>
            <button type="submit" class="btn btn-secondary" name="format" value="xlsx">Download Excel</button>
            <button type="submit" class="btn btn-secondary" name="format" value="json">Download JSON</button>
        </div>
    </form>
</div>
<p class="chart-note" style="margin-top:1rem;">
    Each row is one landlord's figures for one financial year — the same shape as the regulator's own file.
    "All raw data" includes every underlying field (not just the computed percentages), so it can be a wide file.
</p>

<?php if ($showPreview): ?>
<div class="section">
    <h2>Preview</h2>
    <?php if ($previewRows === []): ?>
        <p class="empty-state">No rows match these filters.</p>
    <?php else: ?>
        <p class="subtitle" style="margin-bottom:.75rem;">
            Showing <?= count($previewRows) ?> of <?= $previewTotal ?> row<?= $previewTotal === 1 ? '' : 's' ?>
            and <?= count($previewColumns) + 6 ?> columns. Download to get the full set.
        </p>
        <div class="card" style="overflow-x:auto;padding:0;">
            <table>
                <thead>
                    <tr>
                        <?php foreach (array_keys($previewRows[0]) as $col): ?>
                            <th><?= h($col) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($previewRows as $row): ?>
                        <tr>
                            <?php foreach ($row as $value): ?>
                                <td><?= h($value !== null ? (string) $value : '') ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>
<?php
$content = ob_get_clean();
render_layout('Export', $content, ['active' => 'export']);
