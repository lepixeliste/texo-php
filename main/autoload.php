<?php

/**
 * autoload.php
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

$current_version = phpversion();
if (version_compare($current_version, '8.0.0', '<')) {
    throw new RuntimeException(sprintf('Server requires PHP version 8.0 or higher. Running on version %s.', $current_version));
}

$vendor = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($vendor)) {
    require_once $vendor;
}

spl_autoload_register(function ($class) {
    $filepath = str_replace('\\', '/', preg_replace_callback('/^\/?(\w+)/', function ($matches) {
        return isset($matches[1]) ? strtolower($matches[1]) : $matches[0];
    }, $class));
    $file = __DIR__ . "/$filepath.php";
    if (file_exists($file)) {
        require_once $file;
    }
});
