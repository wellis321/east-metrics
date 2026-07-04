<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/metrics.php';

require_login();

$pdo = db();
$erId = er_landlord_id($pdo);
$years = available_financial_years($pdo);
$catalog = key_indicator_catalog($pdo);

if ($erId === null || $years === []) {
    ob_start();
    ?>
    <h1>Compare</h1>
    <p class="empty-state">No data has been imported yet.</p>
    <?php
    render_layout('Compare', ob_get_clean(), ['active' => 'compare']);
    exit;
}

$year = $_GET['year'] ?? end($years);
if (!in_array($year, $years, true)) {
    $year = end($years);
}

$landlords = all_landlords($pdo);
$selectedPeerIds = array_map('intval', $_GET['peers'] ?? []);
$selectedPeerIds = array_values(array_filter($selectedPeerIds, static fn ($id) => $id !== $erId));

$compareIds = array_merge([$erId], $selectedPeerIds);
$compareNames = [];
foreach ($landlords as $l) {
    if (in_array((int) $l['id'], $compareIds, true)) {
        $compareNames[(int) $l['id']] = $l['name'];
    }
}

if (($_GET['download'] ?? '') === 'csv') {
    $safeYear = str_replace('/', '_', $year);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="compare-' . $safeYear . '.csv"');
    $out = fopen('php://output', 'w');

    $headerRow = ['Indicator'];
    foreach ($compareIds as $id) {
        $headerRow[] = $compareNames[$id] ?? '';
    }
    $headerRow[] = 'Scotland avg';
    fputcsv($out, $headerRow, ',', '"', '\\');

    foreach ($catalog as $ind) {
        $row = [$ind['short_label']];
        foreach ($compareIds as $id) {
            $val = indicator_value_for($pdo, $id, $year, $ind['column_name']);
            $row[] = fmt_value($val, $ind['unit']);
        }
        $avg = scotland_average($pdo, $year, $ind['column_name']);
        $row[] = fmt_value($avg !== null ? (string) $avg : null, $ind['unit']);
        fputcsv($out, $row, ',', '"', '\\');
    }
    fclose($out);
    exit;
}

ob_start();
?>
<h1>Compare</h1>
<p class="subtitle">East Renfrewshire against chosen peer landlords for a single year.</p>

<form method="GET" class="filters" id="compare-form">
    <div class="form-row" style="margin-bottom:0;">
        <label for="year">Financial year</label>
        <select id="year" name="year" onchange="this.form.submit()">
            <?php foreach (array_reverse($years) as $y): ?>
                <option value="<?= h($y) ?>" <?= $y === $year ? 'selected' : '' ?>><?= h($y) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-row peer-picker" data-peer-picker style="margin-bottom:0;">
        <label>Peer landlords</label>
        <button type="button" class="btn btn-secondary btn-sm" data-peer-toggle>+ Add landlords</button>

        <div class="peer-picker-panel" data-peer-panel hidden>
            <div class="peer-picker-panel-header" data-peer-header>
                <span>Choose landlords to compare</span>
                <button type="button" class="peer-picker-panel-close" data-peer-close aria-label="Close">×</button>
            </div>
            <div class="peer-picker-panel-body">
                <div class="peer-chips" data-peer-chips></div>
                <input type="text" data-peer-search placeholder="Search landlords…" autocomplete="off">
                <div class="peer-picker-list" data-peer-list>
                    <?php foreach ($landlords as $l): ?>
                        <?php if ((int) $l['id'] === $erId) continue; ?>
                        <label class="peer-picker-item" data-name="<?= h(strtolower($l['name'])) ?>">
                            <input type="checkbox" name="peers[]" value="<?= (int) $l['id'] ?>"
                                <?= in_array((int) $l['id'], $selectedPeerIds, true) ? 'checked' : '' ?>>
                            <?= h($l['name']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <button type="submit" class="btn btn-secondary">Update comparison</button>
</form>

<script src="<?= h(asset_url('/assets/js/peer-picker.js')) ?>"></script>

<p style="margin-bottom:.75rem;">
    <a class="btn btn-secondary btn-sm" href="?<?= h(http_build_query(array_merge($_GET, ['download' => 'csv']))) ?>">
        ⬇ Download this table (CSV)
    </a>
</p>

<div class="card" id="compare-results" style="overflow-x:auto;">
    <table>
        <thead>
            <tr>
                <th>Indicator</th>
                <?php foreach ($compareIds as $id): ?>
                    <th><?= h($compareNames[$id] ?? '') ?></th>
                <?php endforeach; ?>
                <th>Scotland avg</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($catalog as $ind): ?>
            <tr>
                <td><?= h($ind['short_label']) ?></td>
                <?php foreach ($compareIds as $id): ?>
                    <?php $val = indicator_value_for($pdo, $id, $year, $ind['column_name']); ?>
                    <td class="<?= $id === $erId ? 'pinned' : '' ?>"><?= h(fmt_value($val, $ind['unit'])) ?></td>
                <?php endforeach; ?>
                <?php $avg = scotland_average($pdo, $year, $ind['column_name']); ?>
                <td><?= h(fmt_value($avg !== null ? (string) $avg : null, $ind['unit'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<script>
// After picking peers and submitting, jump straight to the results table —
// otherwise the tall picker list leaves it below the fold.
if (window.location.search) {
    document.getElementById('compare-results').scrollIntoView({ behavior: 'smooth', block: 'start' });
}
</script>
<?php
$content = ob_get_clean();
render_layout('Compare', $content, ['active' => 'compare']);
