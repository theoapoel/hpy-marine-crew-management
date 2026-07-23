<?php

/**
 * Router for `php -S 127.0.0.1:8899 server.php`.
 *
 * The built-in server hands every request to the router script, so files that really
 * exist under public/ (logo, favicon, uploads) have to be short-circuited here —
 * otherwise Laravel answers them and redirects to the login page.
 */
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

if ($uri !== '/' && file_exists(__DIR__ . '/public' . $uri)) {
    return false;
}

require_once __DIR__ . '/public/index.php';
