<?php

use Core\Http\Request;
use Core\Http\Stream;
use Core\Env;
use Core\Container;
use Core\App;

/**
 * bootstrap.php
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

Env::boot();

ini_set('display_errors', getenv('APP_DISPLAY_ERRORS') === 'true');
ini_set('memory_limit', getenv('APP_MEMORY_LIMIT') ?? '256M');
ini_set('default_charset', getenv('APP_CHARSET'));
date_default_timezone_set(getenv('APP_TIMEZONE'));
setlocale(LC_ALL, getenv('APP_LOCALE'));

$container = new Container();
$app = $container->get('Core\App');
if (!($app instanceof App)) {
    throw new RuntimeException('App could not be instantiated.');
}

$stream_input = new Stream(fopen('php://input', 'r'));
$request = new Request(
    get_request_method(),
    get_request_url(),
    apache_request_headers(),
    $stream_input
);

$app->boot($request);

return $app;
