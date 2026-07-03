<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/metrics.php';

require_login();

$pdo = db();
$years = available_financial_years($pdo);
$landlords = all_landlords($pdo);

$filterYear = $_GET['year'] ?? '';
$filterLandlord = isset($_GET['landlord']) && $_GET['landlord'] !== '' ? (int) $_GET['landlord'] : null;
$filterType = $_GET['type'] ?? '';

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 30;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];
if ($filterYear !== '') {
    $where[] = 'ce.financial_year = ?';
    $params[] = $filterYear;
}
if ($filterLandlord !== null) {
    $where[] = 'ce.landlord_id = ?';
    $params[] = $filterLandlord;
}
if (in_array($filterType, ['new_year_data', 'revised_prior_year', 'new_landlord'], true)) {
    $where[] = 'ce.change_type = ?';
    $params[] = $filterType;
}
$whereSql = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM change_events ce $whereSql");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));

$stmt = $pdo->prepare(
    "SELECT ce.*, l.name AS landlord_name FROM change_events ce
       JOIN landlords l ON l.id = ce.landlord_id
       $whereSql
       ORDER BY ce.created_at DESC, ce.id DESC
       LIMIT $perPage OFFSET $offset"
);
$stmt->execute($params);
$events = $stmt->fetchAll();

function qs(array $overrides): string
{
    $params = array_merge($_GET, $overrides);
    $params = array_filter($params, static fn ($v) => $v !== '' && $v !== null);

    return '?' . http_build_query($params);
}

ob_start();
?>
<h1>Changelog</h1>
<p class="subtitle">Every change recorded when a new SHR data set was imported — new years, revised figures, and new landlords.</p>

<form method="GET" class="filters">
    <div class="form-row" style="margin-bottom:0;">
        <label for="year">Financial year</label>
        <select id="year" name="year" onchange="this.form.submit()">
            <option value="">All years</option>
            <?php foreach (array_reverse($years) as $y): ?>
                <option value="<?= h($y) ?>" <?= $y === $filterYear ? 'selected' : '' ?>><?= h($y) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-row" style="margin-bottom:0;">
        <label for="landlord">Landlord</label>
        <select id="landlord" name="landlord" onchange="this.form.submit()">
            <option value="">All landlords</option>
            <?php foreach ($landlords as $l): ?>
                <option value="<?= (int) $l['id'] ?>" <?= $filterLandlord === (int) $l['id'] ? 'selected' : '' ?>><?= h($l['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-row" style="margin-bottom:0;">
        <label for="type">Change type</label>
        <select id="type" name="type" onchange="this.form.submit()">
            <option value="">All types</option>
            <option value="new_year_data" <?= $filterType === 'new_year_data' ? 'selected' : '' ?>>New year data</option>
            <option value="revised_prior_year" <?= $filterType === 'revised_prior_year' ? 'selected' : '' ?>>Revised prior year</option>
            <option value="new_landlord" <?= $filterType === 'new_landlord' ? 'selected' : '' ?>>New landlord</option>
        </select>
    </div>
</form>

<div class="card">
    <?php if ($events === []): ?>
        <p class="empty-state">No changes match these filters.</p>
    <?php else: ?>
        <?php foreach ($events as $c): ?>
            <div class="change-row">
                <div>
                    <?php if ($c['change_type'] === 'new_landlord'): ?>
                        <span class="badge badge-landlord">New landlord</span>
                    <?php elseif ($c['change_type'] === 'revised_prior_year'): ?>
                        <span class="badge badge-revised">Revised</span>
                    <?php else: ?>
                        <span class="badge badge-new">New year</span>
                    <?php endif; ?>
                    <strong><?= h($c['landlord_name']) ?></strong> —
                    <?= h(preg_replace('/^\d+(\s*&\s*\d+)?\s*-\s*/', '', $c['column_name']) ?? $c['column_name']) ?>
                    <span class="change-col">(<?= h($c['financial_year']) ?>)</span>
                </div>
                <div class="change-col">
                    <?= h((string) $c['previous_value']) ?> &rarr; <?= h((string) $c['new_value']) ?>
                    <?php if ($c['pct_change'] !== null): ?> (<?= h((string) $c['pct_change']) ?>%)<?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if ($totalPages > 1): ?>
<div class="pagination">
    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <?php if ($p === $page): ?>
            <span class="current"><?= $p ?></span>
        <?php else: ?>
            <a href="<?= h(qs(['page' => $p])) ?>"><?= $p ?></a>
        <?php endif; ?>
    <?php endfor; ?>
</div>
<?php endif; ?>
<?php
$content = ob_get_clean();
render_layout('Changelog', $content, ['active' => 'changelog']);
