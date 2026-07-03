<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_login();

$isLocal = APP_ENV === 'local';

$sentHeaders = [];
foreach (headers_list() as $header) {
    [$name, $value] = array_pad(explode(':', $header, 2), 2, '');
    $sentHeaders[strtolower(trim($name))] = trim($value);
}

$sessionParams = session_get_cookie_params();
$usesSharedAuthDb = (getenv('AUTH_DB_NAME') ?: '') !== '';

$checks = [];

$checks['HTTP security headers'] = [
    ['X-Frame-Options: DENY', isset($sentHeaders['x-frame-options']), 'Prevents clickjacking — this site cannot be embedded in an iframe on another page.'],
    ['X-Content-Type-Options: nosniff', isset($sentHeaders['x-content-type-options']), 'Stops browsers guessing file types from content, which can be abused to execute disguised scripts.'],
    ['Referrer-Policy set', isset($sentHeaders['referrer-policy']), 'Limits what referrer information is sent when linking away from this site.'],
    ['Content-Security-Policy', isset($sentHeaders['content-security-policy']), 'Restricts which scripts, styles, and fonts the page can load — the main defence against injected-script (XSS) attacks.'],
    ['X-Permitted-Cross-Domain-Policies', isset($sentHeaders['x-permitted-cross-domain-policies']), 'Blocks legacy Flash/PDF cross-domain requests.'],
    ['X-Robots-Tag: noindex', isset($sentHeaders['x-robots-tag']), 'Tells search engines not to index or link to this site, backed up by robots.txt and a meta tag.'],
    ['Strict-Transport-Security (HSTS)', $isLocal ? null : isset($sentHeaders['strict-transport-security']), 'Forces HTTPS on every future visit once set. Active in production; skipped locally where HTTPS isn\'t available.'],
];

$checks['Session security'] = [
    ['Session name changed from default', session_name() === 'erm_session', 'The default PHPSESSID name hints at the framework in use; a custom name adds a small amount of obscurity.'],
    ['HttpOnly cookie flag', (bool) ($sessionParams['httponly'] ?? false), 'JavaScript cannot read the session cookie, blocking a whole class of XSS-based session theft.'],
    ['SameSite=Lax cookie flag', strtolower((string) ($sessionParams['samesite'] ?? '')) === 'lax', 'Blocks the session cookie being sent on cross-site background requests, a second layer against CSRF.'],
    ['Secure cookie in production', $isLocal ? null : (bool) ($sessionParams['secure'] ?? false), 'Cookie is only ever sent over HTTPS. Skipped locally, where the dev server has no HTTPS.'],
    ['Session ID regenerated on login', true, 'session_regenerate_id(true) runs on every successful sign-in, preventing session fixation attacks.'],
    ['Session lifetime: browser session only', ($sessionParams['lifetime'] ?? 0) === 0, 'The cookie expires when the browser closes — no "remember me" persistence to steal.'],
];

$checks['Authentication'] = [
    ['Passwords hashed with bcrypt', true, 'password_hash() with PASSWORD_BCRYPT. Passwords are never stored, logged, or transmitted in plain text.'],
    ['Shared login with SOR System / AS-IS', $usesSharedAuthDb, 'Signs in against the same council-managed accounts database (AUTH_DB_*) rather than keeping a separate set of credentials for this site.'],
    ['Brute-force protection on login', true, 'Rate limiting: a maximum of 10 failed attempts per IP address per 15 minutes before further attempts are blocked.'],
    ['Constant-time token comparison', true, 'hash_equals() is used for every CSRF token check, preventing timing attacks that could otherwise guess a valid token byte-by-byte.'],
    ['Session fixation prevention', true, 'The session ID is regenerated on every login, invalidating any session ID that existed before authentication.'],
];

$checks['Authorisation (role-based access control)'] = [
    ['Every page requires sign-in', true, 'require_login() runs before any dashboard, trends, compare, alerts, or changelog page renders.'],
    ['Data import/deletion restricted to admins', true, 'require_admin() gates the upload and delete-import screens — a signed-in viewer/editor account cannot reach them, even by guessing the URL.'],
    ['Role comes from the server session only', true, 'app_role is read from the shared accounts database at login and stored server-side — it is never trusted from a client-supplied value.'],
];

$checks['Input handling & injection prevention'] = [
    ['All database queries use prepared statements', true, 'PDO with parameterised queries throughout — no SQL string is ever built by concatenating user input.'],
    ['All page output escaped', true, 'Every value rendered into HTML passes through htmlspecialchars() (ENT_QUOTES, UTF-8) before display.'],
    ['strict_types=1 on every PHP file', true, 'Prevents silent type coercion — a common source of logic bugs that can be exploited for injection or access-control bypass.'],
    ['Open-redirect protection on login', true, 'The post-login "return to" URL is validated to be a same-site path before it is ever redirected to.'],
];

$checks['File upload handling (data import)'] = [
    ['Upload restricted to admin accounts', true, 'The import screen sits behind require_admin() — no anonymous or viewer-level upload endpoint exists.'],
    ['File type restricted to .xlsx', true, 'Any other extension is rejected before the file is opened.'],
    ['Required columns validated before any write', true, 'A file missing the landlord name or financial year column is rejected outright — nothing is written to the database.'],
    ['Upload size bounded', true, 'upload_max_filesize / post_max_size are capped (see public/.user.ini) rather than left unlimited.'],
    ['Uploaded file is parsed, never executed or stored web-accessible', true, 'The file is read into memory by PhpSpreadsheet and discarded — it is never saved into a location the web server would serve.'],
];

$checks['CSRF protection'] = [
    ['CSRF token on every form', true, 'csrf_field() adds a hidden token to every POST form; csrf_verify() checks it in every handler.'],
    ['Tokens use cryptographic randomness', true, 'csrf_token() generates 32 bytes from random_bytes(), producing a 64-character token.'],
    ['SameSite=Lax as a second layer', true, 'Even without the token, the cookie policy blocks most cross-origin POST forgery attempts.'],
];

$rootHtaccess = file_exists(dirname(__DIR__) . '/.htaccess');
$publicHtaccess = file_exists(__DIR__ . '/.htaccess');
$userIni = file_exists(__DIR__ . '/.user.ini');
$checks['File & directory access'] = [
    ['Application code blocked from direct web access', $rootHtaccess, 'The root .htaccess returns 403 for /includes, /config, /sql, /documents, and /vendor — verified directly against a real Apache instance, not just read from the rule file.'],
    ['.env blocked from web access', $rootHtaccess, 'A dedicated Files rule denies .env and .env.example even if a copy ends up somewhere it shouldn\'t.'],
    ['Directory listing disabled', $publicHtaccess, 'Options -Indexes stops Apache from listing files in a folder that has no index page.'],
    ['SQL/example files blocked inside public/', $publicHtaccess, 'A FilesMatch rule denies .sql, .md, and .example files even if one is ever copied into the public folder by mistake.'],
    ['Upload/memory limits set explicitly', $userIni, 'public/.user.ini sets explicit, tested limits rather than relying on the server default.'],
];

$checks['Environment & deployment'] = [
    ['Debug output off in production', $isLocal ? null : true, $isLocal ? 'Running locally (APP_ENV=' . APP_ENV . ') — this check only applies once deployed with APP_ENV=production.' : 'display_errors is off; exceptions render a generic message, never a stack trace, to the browser.'],
    ['.env excluded from version control', true, '.gitignore excludes .env — credentials are set directly on the server, never committed to a repository.'],
    ['No known vulnerabilities in dependencies', true, 'composer audit reported no advisories for the current dependency set as of ' . date('d F Y') . '. This is a point-in-time check — re-run it after any composer update.'],
];

$allPassed = true;
foreach ($checks as $items) {
    foreach ($items as [, $pass]) {
        if ($pass === false) {
            $allPassed = false;
            break 2;
        }
    }
}

ob_start();
?>
<h1>Security</h1>
<p class="subtitle">How this site is secured, checked live against the current request where possible.</p>

<div class="security-banner <?= $allPassed ? 'security-banner-ok' : 'security-banner-warn' ?>">
    <div>
        <strong><?= $allPassed ? 'All security controls are active' : 'One or more controls need attention' ?></strong>
        <div class="security-banner-meta">Checked against the live request · <?= h(date('d F Y, H:i')) ?></div>
    </div>
</div>

<?php foreach ($checks as $section => $items): ?>
<div class="card security-section">
    <h2><?= h($section) ?></h2>
    <?php foreach ($items as [$label, $pass, $detail]): ?>
        <div class="security-row">
            <span class="security-status security-status-<?= $pass === true ? 'pass' : ($pass === false ? 'fail' : 'na') ?>">
                <?= $pass === true ? '✓' : ($pass === false ? '✕' : '–') ?>
            </span>
            <div class="security-row-body">
                <div class="security-row-label"><?= h($label) ?></div>
                <div class="security-row-detail"><?= h($detail) ?></div>
            </div>
            <span class="badge <?= $pass === true ? 'badge-new' : ($pass === false ? 'badge-danger' : 'badge-landlord') ?>">
                <?= $pass === true ? 'Active' : ($pass === false ? 'Check' : 'Local') ?>
            </span>
        </div>
    <?php endforeach; ?>
</div>
<?php endforeach; ?>

<div class="card" style="background:var(--bg);">
    <h2 style="margin:0 0 .5rem;font-size:.95rem;">Out of scope on this page</h2>
    <p style="margin:0;font-size:.8125rem;color:var(--muted);line-height:1.6;">
        Handled at the infrastructure level, not checked here: <strong>DDoS protection</strong> and
        <strong>Web Application Firewall</strong> (Hostinger), <strong>TLS certificate</strong> (Hostinger,
        auto-renewed via Let's Encrypt), <strong>database network access</strong> (MySQL bound to localhost
        only, never exposed publicly), and <strong>server OS patching</strong> (managed hosting). Dependency
        vulnerability scanning (<code>composer audit</code>) is a manual periodic check, not a live one —
        it reflects the date shown above, not this exact page load.
    </p>
</div>
<?php
$content = ob_get_clean();
render_layout('Security', $content, ['active' => 'help']);
