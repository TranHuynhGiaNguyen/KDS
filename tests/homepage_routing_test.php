<?php

require_once __DIR__ . '/../includes/auth.php';

function assertHomepageRoute(string $expected, string $page, string $scenario): void
{
    if ($page !== $expected) {
        throw new RuntimeException($scenario . ': expected "' . $expected . '", got "' . $page . '".');
    }
    echo "[PASS] {$scenario}\n";
}

$_SESSION = [];
$page = '';
enforceAuthorization($page);
assertHomepageRoute('home', $page, 'Unauthenticated root opens the homepage.');

$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$page = '';
enforceAuthorization($page);
assertHomepageRoute('home', $page, 'Authenticated root opens the homepage.');

$page = 'login';
enforceAuthorization($page);
assertHomepageRoute('admin', $page, 'Explicit login route preserves the admin management landing page.');
