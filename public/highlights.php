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
    <h1>Highlights</h1>
    <p class="empty-state">No data has been imported yet.</p>
    <?php
    $content = ob_get_clean();
    render_layout('Highlights', $content, ['active' => 'highlights']);
    exit;
}

$currentYear = end($years);
$yearIndex = array_search($currentYear, $years, true);
$previousYear = $yearIndex > 0 ? $years[$yearIndex - 1] : null;

$highlights = dashboard_highlights($pdo, $erId, $currentYear, $previousYear);
$aboveAverage = array_values(array_filter($highlights, static fn ($a) => $a['is_above_average']));
$pullingAhead = array_values(array_filter($highlights, static fn ($a) => $a['is_pulling_ahead']));
$improving = array_values(array_filter($highlights, static fn ($a) => $a['is_improving']));

$validFlags = ['above_average', 'pulling_ahead', 'improving'];
$flag = $_GET['flag'] ?? '';
if (!in_array($flag, $validFlags, true)) {
    $flag = '';
}

$displayed = match ($flag) {
    'above_average' => $aboveAverage,
    'pulling_ahead' => $pullingAhead,
    'improving' => $improving,
    default => $highlights,
};
$flagLabels = ['above_average' => 'Above Scotland average', 'pulling_ahead' => 'Pulling ahead of the average', 'improving' => 'Improving vs prior year'];
?>
<h1>Highlights</h1>
<p class="subtitle">
    East Renfrewshire indicators for <?= h($currentYear) ?> that are above the Scotland average, pulling
    further ahead of it, or improving year-on-year — the mirror image of <a href="/alerts.php">Alerts</a>,
    out of the <?= count(alertable_indicator_catalog($pdo)) ?> indicators with a clear "higher/lower is
    better" direction.
</p>

<?php if ($highlights === []): ?>
    <div class="card empty-state">
        <p>Nothing stands out right now — no tracked indicator is above the Scotland average, pulling
        further ahead, or improving on last year.</p>
    </div>
<?php else: ?>
    <div class="kpi-grid" style="margin-bottom:1.25rem;">
        <a class="kpi-card kpi-card-link <?= $flag === 'above_average' ? 'kpi-card-active' : '' ?>" href="?flag=above_average">
            <div class="kpi-label"><?= icon_above_average() ?> Above Scotland average</div>
            <div class="kpi-value kpi-delta-up"><?= count($aboveAverage) ?></div>
        </a>
        <a class="kpi-card kpi-card-link <?= $flag === 'pulling_ahead' ? 'kpi-card-active' : '' ?>" href="?flag=pulling_ahead">
            <div class="kpi-label"><?= icon_pulling_ahead() ?> Pulling ahead of the average</div>
            <div class="kpi-value" style="color:#0e7490;"><?= count($pullingAhead) ?></div>
        </a>
        <a class="kpi-card kpi-card-link <?= $flag === 'improving' ? 'kpi-card-active' : '' ?>" href="?flag=improving">
            <div class="kpi-label"><?= icon_improving() ?> Improving vs prior year</div>
            <div class="kpi-value" style="color:#1d4ed8;"><?= count($improving) ?></div>
        </a>
    </div>

    <?php if ($flag !== ''): ?>
        <p class="subtitle" style="margin:0 0 1rem;">
            Showing only <strong><?= h($flagLabels[$flag]) ?></strong> —
            <a href="?">show all <?= count($highlights) ?></a>
        </p>
    <?php endif; ?>

    <div class="card">
        <?php if ($displayed === []): ?>
            <p class="empty-state">No indicators match this filter.</p>
        <?php else: ?>
        <table class="sticky-thead">
            <thead>
                <tr>
                    <th>Indicator</th>
                    <th>This year</th>
                    <th>Prior year</th>
                    <th>Scotland avg</th>
                    <th>Flags</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($displayed as $a): ?>
                <?php
                    $trendUrl = '/trends.php?indicator=' . urlencode($a['column_name']) . '&from=highlights';
                    if ($flag !== '') {
                        $trendUrl .= '&flag=' . urlencode($flag);
                    }
                ?>
                <tr class="clickable-row" data-href="<?= h($trendUrl) ?>">
                    <td>
                        <a href="<?= h($trendUrl) ?>" class="clickable-row-link"><?= h($a['short_label']) ?></a>
                        <div class="change-col" style="font-size:.75rem;"><?= h($a['category']) ?></div>
                    </td>
                    <td><?= h(fmt_value($a['current'] !== null ? (string) $a['current'] : null, $a['unit'])) ?></td>
                    <td><?= h(fmt_value($a['previous'] !== null ? (string) $a['previous'] : null, $a['unit'])) ?></td>
                    <td><?= h(fmt_value($a['scotland_avg'] !== null ? (string) $a['scotland_avg'] : null, $a['unit'])) ?></td>
                    <td>
                        <div class="flag-stack">
                        <?php if ($a['is_above_average']): ?><span class="badge badge-new"><?= icon_above_average() ?> Above average</span><?php endif; ?>
                        <?php if ($a['is_pulling_ahead']): ?><span class="badge badge-teal"><?= icon_pulling_ahead() ?> Pulling ahead</span><?php endif; ?>
                        <?php if ($a['is_improving']): ?><span class="badge badge-landlord"><?= icon_improving() ?> Improving</span><?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="chart-note">Click any row to see its trend over time.</p>
        <?php endif; ?>
    </div>
<?php endif; ?>
<script>
document.querySelectorAll('tr.clickable-row[data-href]').forEach(function (row) {
    row.addEventListener('click', function (e) {
        if (e.target.closest('a')) {
            return;
        }
        window.location = row.dataset.href;
    });
});
</script>
<?php
$content = ob_get_clean();
render_layout('Highlights', $content, ['active' => 'highlights']);
