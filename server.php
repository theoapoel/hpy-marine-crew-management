<?php

/**
 * Router for `php -S 127.0.0.1:8899 server.php`.
 *
 * The built-in server hands every request to the router script, so files that really
 * exist under public/ (built css/js, logo, favicon, uploads) have to be answered here —
 * otherwise Laravel answers them and redirects to the login page. They are streamed
 * directly rather than `return false`, because the server's document root is the
 * project root, not public/, and would not find them.
 */
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = __DIR__ . '/public' . $uri;

if ($uri !== '/' && is_file($file) && ! str_ends_with($file, '.php')) {
    $types = [
        'css' => 'text/css', 'js' => 'text/javascript', 'mjs' => 'text/javascript', 'json' => 'application/json',
        'map' => 'application/json', 'svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'ico' => 'image/x-icon', 'woff2' => 'font/woff2',
        'woff' => 'font/woff', 'html' => 'text/html', 'txt' => 'text/plain',
    ];
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

    header('Content-Type: ' . ($types[$ext] ?? (mime_content_type($file) ?: 'application/octet-stream')));
    header('Content-Length: ' . filesize($file));
    readfile($file);

    return true;
}

require_once __DIR__ . '/public/index.php';
