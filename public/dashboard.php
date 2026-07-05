<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/metrics.php';

require_login();

$pdo = db();
$erId = er_landlord_id($pdo);
$years = available_financial_years($pdo);

ob_start();

if ($erId === null || $years === []) {
    ?>
    <h1>Dashboard</h1>
    <div class="empty-state">
        <p>No data has been imported yet.</p>
        <?php if (($_SESSION['app_role'] ?? '') === 'admin'): ?>
            <p><a class="btn" href="/admin/import.php">Import the SHR data set</a></p>
        <?php endif; ?>
    </div>
    <?php
    $content = ob_get_clean();
    render_layout('Dashboard', $content, ['active' => 'dashboard']);
    exit;
}

$currentYear = end($years);
$yearIndex = array_search($currentYear, $years, true);
$previousYear = $yearIndex > 0 ? $years[$yearIndex - 1] : null;

$kpis = dashboard_kpis($pdo, $erId, $currentYear, $previousYear);
$grouped = group_kpis_by_category($kpis);
$dailyMetrics = todays_daily_metrics($pdo, $erId);
?>
<h1><?= h(landlord_name($pdo, $erId) ?? 'East Renfrewshire Council') ?></h1>
<p class="subtitle">Housing Charter performance for <?= h($currentYear) ?><?= $previousYear ? ' (vs ' . h($previousYear) . ')' : '' ?></p>

<?php if ($dailyMetrics !== []): ?>
<div class="category-block">
    <div class="category-title">Today <span class="category-count">· daily operational figures</span></div>
    <div class="kpi-grid">
        <?php foreach ($dailyMetrics as $metric): ?>
            <?php
                $deltaClass = 'kpi-delta-flat';
                $deltaText = '';
                if ($metric['delta'] !== null && abs($metric['delta']) > 0.001) {
                    $deltaClass = $metric['delta'] > 0 ? 'kpi-delta-up' : 'kpi-delta-down';
                    $deltaText = fmt_delta($metric['delta'], $metric['unit']);
                }
            ?>
            <div class="kpi-card">
                <div class="kpi-label"><?= h($metric['short_label']) ?></div>
                <div class="kpi-value"><?= h(fmt_value($metric['value'], $metric['unit'])) ?></div>
                <div class="kpi-meta">
                    <?php if ($deltaText !== ''): ?><span class="<?= $deltaClass ?>"><?= h($deltaText) ?> vs 7 days ago</span><?php endif; ?>
                    <span>as of <?= h($metric['metric_date']) ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="form-row" style="max-width:360px;">
    <label for="dashboard-search">Search indicators</label>
    <input type="text" id="dashboard-search" placeholder="e.g. repairs, satisfaction, rent…" autocomplete="off">
</div>

<p class="empty-state" id="dashboard-no-results" hidden>No indicators match your search.</p>

<?php foreach ($grouped as $category => $items): ?>
    <div class="category-block" data-category-block>
        <div class="category-title"><?= h($category) ?> <span class="category-count">· <?= count($items) ?> indicator<?= count($items) === 1 ? '' : 's' ?></span></div>
        <div class="kpi-grid">
            <?php foreach ($items as $kpi): ?>
                <?php
                    $deltaClass = 'kpi-delta-flat';
                    $deltaText = '';
                    if ($kpi['delta'] !== null && abs($kpi['delta']) > 0.001) {
                        $deltaClass = $kpi['delta'] > 0 ? 'kpi-delta-up' : 'kpi-delta-down';
                        $deltaText = fmt_delta($kpi['delta'], $kpi['unit']);
                    }
                    $searchText = strtolower($kpi['short_label'] . ' ' . $category);
                ?>
                <a class="kpi-card kpi-card-link" data-search="<?= h($searchText) ?>"
                   href="/trends.php?indicator=<?= urlencode($kpi['column_name']) ?>&from=dashboard">
                    <div class="kpi-label"><?= h($kpi['short_label']) ?></div>
                    <div class="kpi-value"><?= h(fmt_value($kpi['current'], $kpi['unit'])) ?></div>
                    <div class="kpi-meta">
                        <?php if ($deltaText !== ''): ?><span class="<?= $deltaClass ?>"><?= h($deltaText) ?> vs prior year</span><?php endif; ?>
                        <?php if ($kpi['scotland_avg'] !== null): ?><span>Scotland avg: <?= h(fmt_value((string) $kpi['scotland_avg'], $kpi['unit'])) ?></span><?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>

<script>
(function () {
    var search = document.getElementById('dashboard-search');
    var blocks = document.querySelectorAll('[data-category-block]');
    var noResults = document.getElementById('dashboard-no-results');

    search.addEventListener('input', function () {
        var q = search.value.trim().toLowerCase();
        var anyVisible = false;

        blocks.forEach(function (block) {
            var cards = block.querySelectorAll('.kpi-card-link');
            var blockHasMatch = false;
            cards.forEach(function (card) {
                var match = card.dataset.search.indexOf(q) !== -1;
                card.style.display = match ? '' : 'none';
                if (match) {
                    blockHasMatch = true;
                }
            });
            block.style.display = blockHasMatch ? '' : 'none';
            if (blockHasMatch) {
                anyVisible = true;
            }
        });

        noResults.hidden = anyVisible;
    });
})();
</script>
<?php
$content = ob_get_clean();
render_layout('Dashboard', $content, ['active' => 'dashboard']);
