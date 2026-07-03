<?php

declare(strict_types=1);

require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/includes/import.php';

require_admin();

$error = null;
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Invalid form submission. Please try again.';
    } elseif (empty($_FILES['xlsx']) || $_FILES['xlsx']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please choose a valid .xlsx file to upload.';
    } else {
        $tmpPath = $_FILES['xlsx']['tmp_name'];
        $originalName = $_FILES['xlsx']['name'];

        if (!str_ends_with(strtolower($originalName), '.xlsx')) {
            $error = 'Only .xlsx files are supported.';
        } else {
            try {
                $parsed = parse_shr_xlsx($tmpPath);
                $result = run_shr_import($parsed, $originalName, get_current_user_id());
                $message = sprintf(
                    'Imported %d rows from "%s" — %d change%s recorded.',
                    $result['row_count'],
                    h($originalName),
                    $result['change_count'],
                    $result['change_count'] === 1 ? '' : 's'
                );
                if ($result['prior_column_count'] > 0 && $result['file_column_count'] < $result['prior_column_count']) {
                    $message .= sprintf(
                        ' Note: this file had %d columns, versus %d seen in earlier imports — the missing columns were left unchanged on any existing records, not cleared.',
                        $result['file_column_count'],
                        $result['prior_column_count']
                    );
                }
                flash('success', $message);
                redirect(app_url('/dashboard.php'));
            } catch (Throwable $e) {
                error_log('SHR import failed: ' . $e->getMessage());
                $error = 'The file could not be imported: ' . $e->getMessage();
            }
        }
    }
}

$imports = db()->query('SELECT * FROM imports ORDER BY uploaded_at DESC LIMIT 10')->fetchAll();

ob_start();
?>
<h1>Import SHR data</h1>
<p class="subtitle">Upload the regulator's annual "Full data set" xlsx. Existing landlord/year rows are
    updated in place; new years and revisions are recorded on the <a href="/changelog.php">changelog</a>.</p>

<?php if ($error !== null): ?>
    <div class="flash flash-error"><?= h($error) ?></div>
<?php endif; ?>

<div class="card" style="max-width:520px;margin-bottom:2rem;">
    <form method="POST" enctype="multipart/form-data" id="import-form">
        <?= csrf_field() ?>
        <div class="form-row">
            <label for="xlsx">SHR Charter data set (.xlsx)</label>
            <input type="file" id="xlsx" name="xlsx" accept=".xlsx" required>
        </div>
        <button type="submit" class="btn" id="import-submit">Upload &amp; import</button>
    </form>
</div>

<div id="import-overlay" class="import-overlay">
    <div class="import-overlay-card">
        <div class="import-spinner" aria-hidden="true"></div>
        <h2>Importing your data…</h2>
        <p>Parsing the file and comparing it against what's already stored can take up to a minute for the
        full data set.</p>
        <p><strong>Please don't close this tab or navigate away</strong> — you'll be taken to the changelog
        automatically once it's done.</p>
    </div>
</div>

<script>
(function () {
    var form = document.getElementById('import-form');
    var overlay = document.getElementById('import-overlay');
    var submitting = false;
    form.addEventListener('submit', function (e) {
        if (submitting) {
            e.preventDefault();
            return;
        }
        submitting = true;
        overlay.classList.add('is-visible');
    });
})();
</script>

<h2>Recent imports</h2>
<?php if ($imports === []): ?>
    <p class="empty-state">No imports yet.</p>
<?php else: ?>
<table>
    <thead>
        <tr><th>Uploaded</th><th>File</th><th>Rows</th><th>Financial years</th><th></th></tr>
    </thead>
    <tbody>
    <?php foreach ($imports as $imp): ?>
        <tr>
            <td><?= h($imp['uploaded_at']) ?></td>
            <td><?= h($imp['filename']) ?></td>
            <td><?= h((string) $imp['row_count']) ?></td>
            <td><?= h((string) $imp['financial_years']) ?></td>
            <td><a href="/admin/delete-import.php?id=<?= (int) $imp['id'] ?>" class="btn btn-sm btn-secondary" style="color:var(--danger);border-color:var(--danger);">Delete</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
<?php
$content = ob_get_clean();
render_layout('Import data', $content, ['active' => 'import']);
