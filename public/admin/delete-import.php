<?php

declare(strict_types=1);

require_once dirname(dirname(__DIR__)) . '/includes/bootstrap.php';
require_once dirname(dirname(__DIR__)) . '/includes/import.php';

require_admin();

$importId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$summary = $importId > 0 ? import_deletion_summary(db(), $importId) : null;

if ($summary === null) {
    flash('error', 'That import no longer exists.');
    redirect(app_url('/admin/import.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        flash('error', 'Invalid form submission. Please try again.');
        redirect(app_url('/admin/import.php'));
    }

    $result = delete_import(db(), $importId);

    $message = sprintf(
        'Deleted import "%s": %d record%s removed entirely.',
        $summary['filename'],
        $result['removed_submissions'],
        $result['removed_submissions'] === 1 ? '' : 's'
    );
    if ($result['unattributed_submissions'] > 0) {
        $message .= sprintf(
            ' %d previously-existing record%s revised by this import were detached from it — their values were not reverted, so re-upload the correct file to fix them.',
            $result['unattributed_submissions'],
            $result['unattributed_submissions'] === 1 ? '' : 's'
        );
    }
    if ($result['removed_landlords'] > 0) {
        $message .= sprintf(' %d landlord%s with no remaining data removed.', $result['removed_landlords'], $result['removed_landlords'] === 1 ? '' : 's');
    }
    flash('success', $message);
    redirect(app_url('/admin/import.php'));
}

ob_start();
?>
<h1>Delete import</h1>
<p class="subtitle">This permanently removes data from the database. This cannot be undone.</p>

<div class="card" style="max-width:560px;">
    <p><strong><?= h($summary['filename']) ?></strong> — uploaded <?= h($summary['uploaded_at']) ?>,
        <?= (int) $summary['row_count'] ?> rows.</p>

    <?php if ($summary['removable_count'] > 0): ?>
        <div class="flash flash-error">
            <?= (int) $summary['removable_count'] ?> landlord/year record<?= $summary['removable_count'] === 1 ? '' : 's' ?>
            that only exist because of this import will be <strong>permanently deleted</strong>.
        </div>
    <?php endif; ?>

    <?php if ($summary['revised_count'] > 0): ?>
        <div class="flash flash-info">
            This import also revised <?= (int) $summary['revised_count'] ?> record<?= $summary['revised_count'] === 1 ? '' : 's' ?>
            that already existed from an earlier import. Deleting it will <strong>not</strong> restore their
            previous values — only the changed headline indicators are kept in the changelog, not every
            underlying field, so a full restore isn't possible. This will just remove them from this
            import's history. Re-upload the correct file afterwards to fix their figures.
        </div>
    <?php endif; ?>

    <?php if ($summary['removable_count'] === 0 && $summary['revised_count'] === 0): ?>
        <p class="empty-state">Nothing is currently attributed to this import — it's already been
            superseded by later uploads. Deleting it will just remove its entry from the import history.</p>
    <?php endif; ?>

    <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $importId ?>">
        <div style="display:flex;gap:.75rem;margin-top:1.5rem;">
            <button type="submit" class="btn" style="background:var(--danger);">Yes, delete this import</button>
            <a class="btn btn-secondary" href="/admin/import.php">Cancel</a>
        </div>
    </form>
</div>
<?php
$content = ob_get_clean();
render_layout('Delete import', $content, ['active' => 'import']);
