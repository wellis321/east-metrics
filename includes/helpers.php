<?php

declare(strict_types=1);

// ── Output escaping ───────────────────────────────────────────────────────────

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// ── Alert flag icons (small inline SVGs, distinct by shape not just colour) ──

function icon_below_average(): string
{
    return '<svg viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="8" y1="1" x2="8" y2="9"/><polyline points="4,6 8,10 12,6"/><line x1="2" y1="14" x2="14" y2="14"/></svg>';
}

function icon_declining(): string
{
    return '<svg viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="2,4 6,9 9,6 14,13"/><polyline points="9,13 14,13 14,8"/></svg>';
}

function icon_closing_gap(): string
{
    return '<svg viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="8" y1="1" x2="8" y2="15" stroke-dasharray="2 2"/><polyline points="2,5 5,8 2,11"/><polyline points="14,5 11,8 14,11"/></svg>';
}

// ── Highlight flag icons — mirror images of the alert icons above ────────────

function icon_above_average(): string
{
    return '<svg viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="8" y1="15" x2="8" y2="7"/><polyline points="4,10 8,6 12,10"/><line x1="2" y1="2" x2="14" y2="2"/></svg>';
}

function icon_improving(): string
{
    return '<svg viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="2,12 6,7 9,10 14,3"/><polyline points="9,3 14,3 14,8"/></svg>';
}

function icon_pulling_ahead(): string
{
    return '<svg viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="8" y1="1" x2="8" y2="15" stroke-dasharray="2 2"/><polyline points="5,5 2,8 5,11"/><polyline points="11,5 14,8 11,11"/></svg>';
}

// ── CSRF ──────────────────────────────────────────────────────────────────────

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function csrf_verify(): bool
{
    $token = $_POST['csrf_token'] ?? '';

    return hash_equals(csrf_token(), $token);
}

// ── Flash messages ────────────────────────────────────────────────────────────

function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function render_flash(): string
{
    if (empty($_SESSION['flash'])) {
        return '';
    }

    $out = '';
    foreach ($_SESSION['flash'] as $f) {
        $cls = match ($f['type']) {
            'success' => 'flash-success',
            'error'   => 'flash-error',
            default   => 'flash-info',
        };
        $out .= '<div class="flash ' . $cls . '">' . h($f['msg']) . '</div>';
    }
    unset($_SESSION['flash']);

    return $out;
}

// ── Formatting ────────────────────────────────────────────────────────────────

function fmt_value(?string $value, string $unit): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    return match ($unit) {
        'percent' => rtrim(rtrim(number_format((float) $value, 1), '0'), '.') . '%',
        'gbp'     => '£' . number_format((float) $value, 0),
        'days', 'hours' => rtrim(rtrim(number_format((float) $value, 1), '0'), '.') . ' ' . $unit,
        'count'   => number_format((float) $value, 0),
        default   => $value,
    };
}

function fmt_delta(?float $delta, string $unit): string
{
    if ($delta === null) {
        return '';
    }
    $sign = $delta > 0 ? '+' : '';

    return $sign . fmt_value((string) $delta, $unit);
}

// ── Layout ────────────────────────────────────────────────────────────────────

/**
 * @param array{active?:string} $options
 */
function render_layout(string $title, string $content, array $options = []): void
{
    $active = $options['active'] ?? '';
    $isLanding = !empty($options['landing']);
    // Core "explore the data" destinations get the primary nav. Changelog,
    // Import data, and Help are data-management/meta pages rather than
    // analysis views, so they live together in the lighter-weight utility
    // strip on the right instead of competing for primary billing.
    $navItems = [
        'dashboard'  => ['/dashboard.php', 'Dashboard'],
        'alerts'     => ['/alerts.php', 'Alerts'],
        'highlights' => ['/highlights.php', 'Highlights'],
        'trends'     => ['/trends.php', 'Trends'],
        'compare'    => ['/compare.php', 'Compare'],
    ];
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
    <title><?= h($title) ?> · East Renfrewshire Housing Metrics</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='6' fill='%23005a44'/%3E%3Ctext x='16' y='22' font-family='system-ui' font-weight='700' font-size='16' text-anchor='middle' fill='white'%3EER%3C/text%3E%3C/svg%3E">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= h(asset_url('/assets/css/app.css')) ?>">
</head>
<body>
<div class="research-notice" role="note">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" width="16" height="16" style="flex-shrink:0;">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 18v-5.25m0 0a6.01 6.01 0 0 0 1.5-.189m-1.5.189a6.01 6.01 0 0 1-1.5-.189m3.75 7.478a12.06 12.06 0 0 1-4.5 0m3.75 2.383a14.406 14.406 0 0 1-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 1 0-7.517 0c.85.493 1.509 1.333 1.509 2.316V18" />
    </svg>
    <span><strong>Research &amp; Learning Resource</strong> — This is a demonstration system built for
        research and learning purposes. It is not an official East Renfrewshire Council reporting tool.</span>
</div>
<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="/index.php">
            <img src="<?= h(asset_url('/assets/images/leaves.webp')) ?>" alt="" class="topbar-leaves" width="28" height="20">
            East Renfrewshire <span>Housing Metrics</span>
        </a>
        <?php if (is_logged_in()): ?>
            <nav class="nav">
                <?php foreach ($navItems as $key => [$href, $label]): ?>
                    <a href="<?= h($href) ?>" class="<?= $active === $key ? 'active' : '' ?>"><?= h($label) ?></a>
                <?php endforeach; ?>
            </nav>
            <div class="topbar-user">
                <div class="topbar-dropdown" data-dropdown>
                    <button type="button" class="topbar-util topbar-dropdown-toggle <?= in_array($active, ['export', 'import', 'import-daily', 'nec-integration'], true) ? 'active' : '' ?>" data-dropdown-toggle>
                        Data
                        <svg viewBox="0 0 16 16" width="10" height="10" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="4,6 8,10 12,6"/></svg>
                    </button>
                    <div class="topbar-dropdown-menu" data-dropdown-menu>
                        <a href="/export.php" class="topbar-dropdown-item <?= $active === 'export' ? 'active' : '' ?>">Export</a>
                        <?php if (($_SESSION['app_role'] ?? '') === 'admin'): ?>
                            <a href="/admin/import.php" class="topbar-dropdown-item <?= $active === 'import' ? 'active' : '' ?>">Import data</a>
                            <a href="/admin/import-daily.php" class="topbar-dropdown-item <?= $active === 'import-daily' ? 'active' : '' ?>">Import daily figures</a>
                            <a href="/admin/nec-integration.php" class="topbar-dropdown-item <?= $active === 'nec-integration' ? 'active' : '' ?>">NEC integration notes</a>
                        <?php endif; ?>
                    </div>
                </div>
                <a href="/help.php" class="topbar-util <?= $active === 'help' ? 'active' : '' ?>">Help</a>
                <div class="topbar-account">
                    <span class="topbar-user-name"><?= h((string) ($_SESSION['admin_user'] ?? '')) ?></span>
                    <a href="/logout.php" class="btn btn-ghost btn-sm">Log out</a>
                </div>
            </div>
        <?php else: ?>
            <nav class="nav"></nav>
            <div class="topbar-user">
                <a href="/help.php" class="topbar-util <?= $active === 'help' ? 'active' : '' ?>">Help</a>
                <a href="/login.php" class="btn btn-ghost btn-sm">Sign in</a>
            </div>
        <?php endif; ?>
    </div>
</header>
<?php if ($isLanding): ?>
<main>
    <?= $content ?>
</main>
<?php else: ?>
<main class="page">
    <div class="page-inner">
        <?= render_flash() ?>
        <?= $content ?>
    </div>
</main>
<?php endif; ?>
<footer class="site-footer">
    <div class="footer-inner">
        <span class="footer-label">ERC Digital Tools</span>
        <a href="<?= h(SOR_SITE_URL) ?>/">SOR Management System</a>
        <a href="<?= h(ERC_SITE_URL) ?>/">ERC Portal</a>
        <a href="<?= h(ASIS_SITE_URL) ?>/">AS-IS Process Mapping</a>
        <a href="<?= h(APP_URL) ?>/">Housing Metrics</a>
    </div>
</footer>
<?php if (is_logged_in()): ?>
<script src="<?= h(asset_url('/assets/js/nav-dropdown.js')) ?>"></script>
<?php endif; ?>
</body>
</html>
    <?php
}

/**
 * Appends a ?v=<mtime> cache-buster so a CSS/JS edit is picked up on the
 * next page load instead of the browser serving a stale cached copy —
 * without needing anyone to hard-refresh.
 */
function asset_url(string $path): string
{
    $urlPath = '/' . ltrim($path, '/');
    $diskPath = dirname(__DIR__) . '/public/' . ltrim($path, '/');

    if (is_file($diskPath)) {
        $urlPath .= '?v=' . filemtime($diskPath);
    }

    return $urlPath;
}
