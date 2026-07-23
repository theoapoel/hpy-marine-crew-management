<?php

/**
 * Router for the built-in dev server:
 *   php -S 127.0.0.1:8899 -t public public/router.php
 *
 * The built-in server hands every request to the router script, so files that really
 * exist under public/ (logo, favicon, storage links) must be short-circuited here —
 * otherwise Laravel answers them and redirects to the login page.
 */
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

if ($uri !== '/' && file_exists(__DIR__ . $uri) && ! is_dir(__DIR__ . $uri)) {
    return false;
}

require_once __DIR__ . '/index.php';
