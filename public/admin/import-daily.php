<?php

declare(strict_types=1);

require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/includes/daily_import.php';

require_admin();

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $source = $_POST['source'] ?? '';

    if (!csrf_verify()) {
        $error = 'Invalid form submission. Please try again.';
    } elseif (!isset(DAILY_IMPORT_PROVIDERS[$source])) {
        $error = 'Please choose a source system.';
    } elseif (empty($_FILES['export']) || $_FILES['export']['error'] !== UPLOAD_ERR_OK) {
        $uploadError = $_FILES['export']['error'] ?? UPLOAD_ERR_NO_FILE;
        $error = match ($uploadError) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => sprintf(
                'That file is too large — this server accepts up to %s (upload_max_filesize) and %s per request (post_max_size). Ask your host or increase these in php.ini.',
                ini_get('upload_max_filesize'),
                ini_get('post_max_size')
            ),
            UPLOAD_ERR_PARTIAL => 'The upload was interrupted partway through. Please try again.',
            UPLOAD_ERR_NO_FILE => 'Please choose a valid .csv file to upload.',
            default => 'Please choose a valid .csv file to upload.',
        };
    } else {
        $tmpPath = $_FILES['export']['tmp_name'];
        $originalName = $_FILES['export']['name'];

        if (!str_ends_with(strtolower($originalName), '.csv')) {
            $error = 'Only .csv files are supported.';
        } else {
            $provider = DAILY_IMPORT_PROVIDERS[$source];

            try {
                require_once $provider['require'];

                $pdo = db();
                $erId = (int) $pdo->query('SELECT id FROM landlords WHERE is_east_renfrewshire = 1 LIMIT 1')->fetchColumn();
                if ($erId === 0) {
                    throw new RuntimeException('No East Renfrewshire landlord found — import the SHR data set first.');
                }

                $rows = $provider['parse_fn']($tmpPath, $erId);
                $result = run_daily_import($rows, strtoupper($source), $originalName, get_current_user_id());

                flash('success', sprintf(
                    'Imported %d daily metric row%s from "%s" (%s).',
                    $result['row_count'],
                    $result['row_count'] === 1 ? '' : 's',
                    h($originalName),
                    h($provider['label'])
                ));
                redirect(app_url('/dashboard.php'));
            } catch (Throwable $e) {
                error_log('Daily import failed: ' . $e->getMessage());
                $error = 'The file could not be imported: ' . $e->getMessage();
            }
        }
    }
}

$recentImports = recent_daily_imports(db());

ob_start();
?>
<h1>Import daily figures</h1>
<p class="subtitle">Upload a daily export from one of the service's own systems (NEC, and later Integra/ROCC/APEX)
    to update the "Today" section on the <a href="/dashboard.php">dashboard</a>. This is separate from the
    regulator's annual <a href="/admin/import.php">SHR data set</a>.</p>

<?php if ($error !== null): ?>
    <div class="flash flash-error"><?= h($error) ?></div>
<?php endif; ?>

<div class="card" style="max-width:520px;margin-bottom:2rem;">
    <form method="POST" enctype="multipart/form-data" id="import-daily-form">
        <?= csrf_field() ?>
        <div class="form-row">
            <label for="source">Source system</label>
            <select id="source" name="source" required>
                <?php foreach (DAILY_IMPORT_PROVIDERS as $key => $provider): ?>
                    <option value="<?= h($key) ?>"><?= h($provider['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-row">
            <label for="export">Daily export (.csv)</label>
            <input type="file" id="export" name="export" accept=".csv" required>
        </div>
        <button type="submit" class="btn" id="import-daily-submit">Upload &amp; import</button>
    </form>
</div>

<h2>Recent daily imports</h2>
<?php if ($recentImports === []): ?>
    <p class="empty-state">No daily imports yet.</p>
<?php else: ?>
<table>
    <thead>
        <tr><th>Uploaded</th><th>File</th><th>Source</th><th>Metric rows</th></tr>
    </thead>
    <tbody>
    <?php foreach ($recentImports as $imp): ?>
        <tr>
            <td><?= h($imp['uploaded_at']) ?></td>
            <td><?= h($imp['filename']) ?></td>
            <td><?= h($imp['sources']) ?></td>
            <td><?= h((string) $imp['metric_row_count']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
<?php
$content = ob_get_clean();
render_layout('Import daily figures', $content, ['active' => 'import-daily']);
