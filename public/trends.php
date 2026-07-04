<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/metrics.php';

require_login();

$pdo = db();
$erId = er_landlord_id($pdo);
$catalog = key_indicator_catalog($pdo);

if ($erId === null || $catalog === []) {
    ob_start();
    ?>
    <h1>Trends</h1>
    <p class="empty-state">No data has been imported yet.</p>
    <?php
    render_layout('Trends', ob_get_clean(), ['active' => 'trends']);
    exit;
}

$selectedColumn = $_GET['indicator'] ?? $catalog[0]['column_name'];
$selectedIndicator = null;
foreach ($catalog as $ind) {
    if ($ind['column_name'] === $selectedColumn) {
        $selectedIndicator = $ind;
        break;
    }
}
if ($selectedIndicator === null) {
    $selectedIndicator = $catalog[0];
    $selectedColumn = $selectedIndicator['column_name'];
}

$landlords = all_landlords($pdo);
$peerIds = array_map('intval', $_GET['peers'] ?? []);
$peerIds = array_values(array_unique(array_filter($peerIds, static fn ($id) => $id !== $erId)));

$years = available_financial_years($pdo);
$erSeries = indicator_series($pdo, $erId, $selectedColumn);
$scotlandSeries = scotland_average_series($pdo, $selectedColumn);

$peers = [];
$noDataNames = [];
foreach ($peerIds as $peerId) {
    $name = landlord_name($pdo, $peerId);
    if ($name === null) {
        continue;
    }
    $series = indicator_series($pdo, $peerId, $selectedColumn);
    $values = array_map(static fn ($y) => is_numeric($series[$y] ?? null) ? (float) $series[$y] : null, $years);
    $peers[] = ['name' => $name, 'values' => $values];
    if (array_filter($values, static fn ($v) => $v !== null) === []) {
        $noDataNames[] = $name;
    }
}

$chartData = [
    'labels' => $years,
    'erName' => landlord_name($pdo, $erId) ?? 'East Renfrewshire',
    'erValues' => array_map(static fn ($y) => is_numeric($erSeries[$y] ?? null) ? (float) $erSeries[$y] : null, $years),
    'scotlandValues' => array_map(static fn ($y) => $scotlandSeries[$y] ?? null, $years),
    'peers' => $peers,
    'unit' => $selectedIndicator['unit'],
];

$cameFrom = $_GET['from'] ?? '';
$backToAlertsFlag = $_GET['flag'] ?? '';
if (!in_array($backToAlertsFlag, ['below_average', 'approaching', 'declining'], true)) {
    $backToAlertsFlag = '';
}
$backLink = match ($cameFrom) {
    'alerts' => ['/alerts.php' . ($backToAlertsFlag !== '' ? '?flag=' . $backToAlertsFlag : ''), 'Back to alerts'],
    'dashboard' => ['/dashboard.php', 'Back to dashboard'],
    default => null,
};

ob_start();
?>
<h1>Trends</h1>
<p class="subtitle">East Renfrewshire's performance over time, against the Scotland-wide average.</p>

<?php if ($backLink !== null): ?>
    <p class="back-link">
        <a href="<?= h($backLink[0]) ?>">&larr; <?= h($backLink[1]) ?></a>
    </p>
<?php endif; ?>

<form method="GET" class="filters">
    <div class="form-row" style="margin-bottom:0;">
        <label for="indicator">Indicator</label>
        <select id="indicator" name="indicator" onchange="this.form.submit()">
            <?php $currentCategory = null; ?>
            <?php foreach ($catalog as $ind): ?>
                <?php if ($ind['category'] !== $currentCategory): ?>
                    <?php if ($currentCategory !== null): ?></optgroup><?php endif; ?>
                    <?php $currentCategory = $ind['category']; ?>
                    <optgroup label="<?= h($currentCategory) ?>">
                <?php endif; ?>
                <option value="<?= h($ind['column_name']) ?>" <?= $ind['column_name'] === $selectedColumn ? 'selected' : '' ?>>
                    <?= h($ind['short_label']) ?>
                </option>
            <?php endforeach; ?>
            <?php if ($currentCategory !== null): ?></optgroup><?php endif; ?>
        </select>
    </div>
    <div class="form-row peer-picker" data-peer-picker style="margin-bottom:0;">
        <label>Compare with</label>
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
                                <?= in_array((int) $l['id'], $peerIds, true) ? 'checked' : '' ?>>
                            <?= h($l['name']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <button type="submit" class="btn btn-secondary">Update chart</button>
</form>

<div class="chart-wrap">
    <canvas id="trendChart" height="90"></canvas>
</div>

<?php if ($noDataNames !== []): ?>
    <p class="chart-note">
        No line shown for <?= h(implode(', ', $noDataNames)) ?> — this indicator has no reported
        value for any year in the data.
    </p>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script src="<?= h(asset_url('/assets/js/peer-picker.js')) ?>"></script>
<script>
const chartData = <?= json_encode($chartData, JSON_THROW_ON_ERROR) ?>;
const ctx = document.getElementById('trendChart');
const peerColors = ['#1d4ed8', '#9333ea', '#c2410c', '#0891b2', '#be185d', '#4d7c0f'];
const datasets = [
    {
        label: chartData.erName,
        data: chartData.erValues,
        borderColor: '#005a44',
        backgroundColor: '#005a44',
        tension: 0.25,
        spanGaps: true,
    },
    {
        label: 'Scotland average',
        data: chartData.scotlandValues,
        borderColor: '#9a6700',
        backgroundColor: '#9a6700',
        borderDash: [6, 4],
        tension: 0.25,
        spanGaps: true,
    },
];
chartData.peers.forEach(function (peer, i) {
    const color = peerColors[i % peerColors.length];
    datasets.push({
        label: peer.name,
        data: peer.values,
        borderColor: color,
        backgroundColor: color,
        tension: 0.25,
        spanGaps: true,
    });
});
new Chart(ctx, {
    type: 'line',
    data: { labels: chartData.labels, datasets },
    options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { position: 'bottom' } },
        scales: { y: { beginAtZero: false } },
    },
});

// After picking an indicator/peers and submitting, jump straight to the
// chart — otherwise the tall picker list leaves it below the fold and it
// looks like nothing happened.
if (window.location.search) {
    document.querySelector('.chart-wrap').scrollIntoView({ behavior: 'smooth', block: 'start' });
}
</script>
<?php
$content = ob_get_clean();
render_layout('Trends', $content, ['active' => 'trends']);
