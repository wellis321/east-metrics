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
    <div class="form-row peer-picker" style="margin-bottom:0;">
        <label for="peer-search">Peer landlords — pick as many as you like</label>
        <div class="peer-chips" id="peer-chips"></div>
        <input type="text" id="peer-search" placeholder="Search landlords…" autocomplete="off">
        <div class="peer-picker-list" id="peer-picker-list">
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
    <button type="submit" class="btn btn-secondary">Update comparison</button>
</form>

<script>
(function () {
    var search = document.getElementById('peer-search');
    var list = document.getElementById('peer-picker-list');
    var chips = document.getElementById('peer-chips');
    var items = list.querySelectorAll('.peer-picker-item');

    function renderChips() {
        chips.innerHTML = '';
        items.forEach(function (item) {
            var box = item.querySelector('input');
            if (box.checked) {
                var chip = document.createElement('span');
                chip.className = 'peer-chip';
                chip.textContent = item.textContent.trim();
                var remove = document.createElement('button');
                remove.type = 'button';
                remove.textContent = '×';
                remove.setAttribute('aria-label', 'Remove ' + item.textContent.trim());
                remove.addEventListener('click', function () {
                    box.checked = false;
                    renderChips();
                });
                chip.appendChild(remove);
                chips.appendChild(chip);
            }
        });
    }

    search.addEventListener('input', function () {
        var q = search.value.trim().toLowerCase();
        items.forEach(function (item) {
            item.style.display = item.dataset.name.includes(q) ? '' : 'none';
        });
    });

    items.forEach(function (item) {
        item.querySelector('input').addEventListener('change', renderChips);
    });

    renderChips();
})();
</script>

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
