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
?>
<h1><?= h(landlord_name($pdo, $erId) ?? 'East Renfrewshire Council') ?></h1>
<p class="subtitle">Housing Charter performance for <?= h($currentYear) ?><?= $previousYear ? ' (vs ' . h($previousYear) . ')' : '' ?></p>

<?php foreach ($grouped as $category => $items): ?>
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
            ?>
            <div class="kpi-card">
                <div class="kpi-label"><?= h($kpi['short_label']) ?></div>
                <div class="kpi-value"><?= h(fmt_value($kpi['current'], $kpi['unit'])) ?></div>
                <div class="kpi-meta">
                    <?php if ($deltaText !== ''): ?><span class="<?= $deltaClass ?>"><?= h($deltaText) ?> vs prior year</span><?php endif; ?>
                    <?php if ($kpi['scotland_avg'] !== null): ?><span>Scotland avg: <?= h(fmt_value((string) $kpi['scotland_avg'], $kpi['unit'])) ?></span><?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endforeach; ?>
<?php
$content = ob_get_clean();
render_layout('Dashboard', $content, ['active' => 'dashboard']);
