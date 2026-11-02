<?php
/**
 * router.php
 * Built-in PHP Development Server Router
 * Command: php -S localhost:8000 router.php
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$filePath = __DIR__ . $uri;

// Serve existing static files
if ($uri !== '/' && file_exists($filePath) && !is_dir($filePath)) {
    return false;
}

// Route API requests
if (preg_match('#^/api(?:/index\.php)?/(.*)$#i', $uri, $matches)) {
    $_GET['__route'] = '/' . ltrim($matches[1], '/');
    require __DIR__ . '/api/index.php';
    return true;
}

if ($uri === '/api' || $uri === '/api/') {
    require __DIR__ . '/api/index.php';
    return true;
}

// Default frontend entry point
if ($uri === '/' || $uri === '/index.html' || $uri === '/index.php') {
    require __DIR__ . '/index.html';
    return true;
}

return false;
