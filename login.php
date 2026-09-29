<?php
/**
 * Root Login Redirector
 * Redirects traffic from root /login.php to the actual portal login.
 */
$queryString = $_SERVER['QUERY_STRING'] ?? '';
$redirectUrl = 'api/portal/login.php' . ($queryString !== '' ? '?' . $queryString : '');
header('Location: ' . $redirectUrl);
exit;
