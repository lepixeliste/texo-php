<?php

/**
 * index.php
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

require_once __DIR__ . '/main/functions.php';
require_once __DIR__ . '/main/autoload.php';

/** @var \Core\App $app */
$app = require_once __DIR__ . '/main/bootstrap.php';
$app->send();
