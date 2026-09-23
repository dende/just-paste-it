<?php

// Let the development server serve existing public assets directly.
$publicDirectory = realpath(__DIR__.'/../public');
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = realpath($publicDirectory.rawurldecode($path));
if ($path !== '/' && $file !== false && str_starts_with($file, $publicDirectory.DIRECTORY_SEPARATOR) && is_file($file)) {
    return false;
}

require $publicDirectory.'/index.php';
