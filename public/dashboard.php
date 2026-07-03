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

$recentChanges = $pdo->prepare(
    'SELECT ce.*, l.name AS landlord_name FROM change_events ce
       JOIN landlords l ON l.id = ce.landlord_id
      WHERE ce.landlord_id = ?
      ORDER BY ce.created_at DESC, ce.id DESC LIMIT 8'
);
$recentChanges->execute([$erId]);
$recentChanges = $recentChanges->fetchAll();
?>
<h1>East Renfrewshire Council</h1>
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

<div class="section">
    <h2>What's changed recently</h2>
    <div class="card">
        <?php if ($recentChanges === []): ?>
            <p class="empty-state">No changes recorded yet.</p>
        <?php else: ?>
            <?php foreach ($recentChanges as $c): ?>
                <div class="change-row">
                    <div>
                        <?php if ($c['change_type'] === 'new_landlord'): ?>
                            <span class="badge badge-landlord">New</span>
                        <?php elseif ($c['change_type'] === 'revised_prior_year'): ?>
                            <span class="badge badge-revised">Revised</span>
                        <?php else: ?>
                            <span class="badge badge-new">New year</span>
                        <?php endif; ?>
                        <strong><?= h(preg_replace('/^\d+(\s*&\s*\d+)?\s*-\s*/', '', $c['column_name']) ?? $c['column_name']) ?></strong>
                        <span class="change-col">(<?= h($c['financial_year']) ?>)</span>
                    </div>
                    <div class="change-col"><?= h((string) $c['previous_value']) ?> &rarr; <?= h((string) $c['new_value']) ?></div>
                </div>
            <?php endforeach; ?>
            <p style="margin:1rem 0 0;"><a href="/changelog.php">View full changelog &rarr;</a></p>
        <?php endif; ?>
    </div>
</div>
<?php
$content = ob_get_clean();
render_layout('Dashboard', $content, ['active' => 'dashboard']);
