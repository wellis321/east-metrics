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
    <h1>Alerts</h1>
    <p class="empty-state">No data has been imported yet.</p>
    <?php
    $content = ob_get_clean();
    render_layout('Alerts', $content, ['active' => 'alerts']);
    exit;
}

$currentYear = end($years);
$yearIndex = array_search($currentYear, $years, true);
$previousYear = $yearIndex > 0 ? $years[$yearIndex - 1] : null;

$alerts = dashboard_alerts($pdo, $erId, $currentYear, $previousYear);
$belowAverage = array_values(array_filter($alerts, static fn ($a) => $a['is_below_average']));
$approaching = array_values(array_filter($alerts, static fn ($a) => $a['is_approaching_average']));
$declining = array_values(array_filter($alerts, static fn ($a) => $a['is_declining']));

$validFlags = ['below_average', 'approaching', 'declining'];
$flag = $_GET['flag'] ?? '';
if (!in_array($flag, $validFlags, true)) {
    $flag = '';
}

$displayed = match ($flag) {
    'below_average' => $belowAverage,
    'approaching' => $approaching,
    'declining' => $declining,
    default => $alerts,
};
$flagLabels = ['below_average' => 'Below Scotland average', 'approaching' => 'Closing in on the average', 'declining' => 'Declining vs prior year'];
?>
<h1>Alerts</h1>
<p class="subtitle">
    East Renfrewshire indicators for <?= h($currentYear) ?> that are declining year-on-year, below the
    Scotland average, or closing in on it — out of the <?= count(alertable_indicator_catalog($pdo)) ?> indicators
    with a clear "higher/lower is better" direction.
</p>

<?php if ($alerts === []): ?>
    <div class="card empty-state">
        <p>Nothing needs attention right now — no tracked indicator is declining, below the Scotland
        average, or losing ground toward it.</p>
    </div>
<?php else: ?>
    <div class="kpi-grid" style="margin-bottom:1.25rem;">
        <a class="kpi-card kpi-card-link <?= $flag === 'below_average' ? 'kpi-card-active' : '' ?>" href="?flag=below_average">
            <div class="kpi-label"><?= icon_below_average() ?> Below Scotland average</div>
            <div class="kpi-value kpi-delta-down"><?= count($belowAverage) ?></div>
        </a>
        <a class="kpi-card kpi-card-link <?= $flag === 'approaching' ? 'kpi-card-active' : '' ?>" href="?flag=approaching">
            <div class="kpi-label"><?= icon_closing_gap() ?> Closing in on the average</div>
            <div class="kpi-value" style="color:var(--warning);"><?= count($approaching) ?></div>
        </a>
        <a class="kpi-card kpi-card-link <?= $flag === 'declining' ? 'kpi-card-active' : '' ?>" href="?flag=declining">
            <div class="kpi-label"><?= icon_declining() ?> Declining vs prior year</div>
            <div class="kpi-value" style="color:#c2410c;"><?= count($declining) ?></div>
        </a>
    </div>

    <?php if ($flag !== ''): ?>
        <p class="subtitle" style="margin:0 0 1rem;">
            Showing only <strong><?= h($flagLabels[$flag]) ?></strong> —
            <a href="?">show all <?= count($alerts) ?></a>
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
                    $trendUrl = '/trends.php?indicator=' . urlencode($a['column_name']) . '&from=alerts';
                    if ($flag !== '') {
                        $trendUrl .= '&flag=' . urlencode($flag);
                    }
                ?>
                <tr class="alert-row" data-href="<?= h($trendUrl) ?>">
                    <td>
                        <a href="<?= h($trendUrl) ?>" class="alert-row-link"><?= h($a['short_label']) ?></a>
                        <div class="change-col" style="font-size:.75rem;"><?= h($a['category']) ?></div>
                    </td>
                    <td><?= h(fmt_value($a['current'] !== null ? (string) $a['current'] : null, $a['unit'])) ?></td>
                    <td><?= h(fmt_value($a['previous'] !== null ? (string) $a['previous'] : null, $a['unit'])) ?></td>
                    <td><?= h(fmt_value($a['scotland_avg'] !== null ? (string) $a['scotland_avg'] : null, $a['unit'])) ?></td>
                    <td class="flag-cell">
                        <?php if ($a['is_below_average']): ?><span class="badge badge-danger"><?= icon_below_average() ?> Below average</span><?php endif; ?>
                        <?php if ($a['is_approaching_average']): ?><span class="badge badge-revised"><?= icon_closing_gap() ?> Closing gap</span><?php endif; ?>
                        <?php if ($a['is_declining']): ?><span class="badge badge-orange"><?= icon_declining() ?> Declining</span><?php endif; ?>
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
document.querySelectorAll('tr.alert-row[data-href]').forEach(function (row) {
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
render_layout('Alerts', $content, ['active' => 'alerts']);
