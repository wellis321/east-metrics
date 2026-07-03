<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect(resolve_login_next($_GET['next'] ?? ''));
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Invalid form submission. Please try again.';
    } elseif (is_rate_limited(client_ip())) {
        $error = 'Too many failed attempts. Please wait 15 minutes and try again.';
    } else {
        $user   = trim($_POST['username'] ?? '');
        $pass   = $_POST['password'] ?? '';
        $result = attempt_login($user, $pass);
        if ($result === true) {
            clear_attempts(client_ip());
            redirect(resolve_login_next($_GET['next'] ?? ''));
        } else {
            record_failed_attempt(client_ip());
            $reason = last_login_fail_reason();
            $error = match ($reason) {
                'no_local_password' => 'This account has no local password set. Ask an administrator for access.',
                'user_not_found'    => 'No matching account found. Try your email address if you sign in with that.',
                default             => 'Incorrect username or password.',
            };
        }
    }
}

ob_start();
?>
<div class="login-wrap">
    <div class="card">
        <h1>Sign in</h1>
        <p class="subtitle">East Renfrewshire Housing Metrics — uses your SOR/AS-IS account</p>

        <?= render_flash() ?>
        <?php if ($error !== ''): ?>
            <div class="flash flash-error"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <?= csrf_field() ?>
            <div class="form-row">
                <label for="username">Username or email</label>
                <input type="text" id="username" name="username" autocomplete="username"
                       value="<?= h($_POST['username'] ?? '') ?>" required autofocus>
            </div>
            <div class="form-row">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" autocomplete="current-password" required>
            </div>
            <button type="submit" class="btn" style="width:100%;">Sign in</button>
        </form>
    </div>
</div>
<?php
$content = ob_get_clean();
render_layout('Sign in', $content);
