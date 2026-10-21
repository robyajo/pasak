<?php

$publicPath = getcwd().'/public';
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '');

// Emulate Apache / Nginx: never serve hidden/protected files as static files
$isProtectedFile = (bool) preg_match('#/(?:\.|\b(?:composer\.(?:json|lock)|package\.json|web\.config))\b#i', $uri);

if (! $isProtectedFile && $uri !== '/' && file_exists($publicPath.$uri)) {
    return false;
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $publicPath.'/index.php';

require_once $publicPath.'/index.php';
