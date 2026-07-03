<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

logout();
redirect(app_url('/login.php'));
