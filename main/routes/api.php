<?php

use App\Controllers\HelloController;
use Core\App;
use Core\Routing\Router;

/**
 * api.php
 *
 * @version 1.0.0
 */

return function (Router $router, App $app) {

    $router
        ->get('/api/info', function () {
            return [
                'name' => getenv('APP_NAME'),
                'version' => appversion(),
                'engine' => 'PHP ' . phpversion(),
                'extensions' => get_loaded_extensions()
            ];
        })

        ->get('/api/changelog', function () {
            $contents = file_get_contents(path_root('logs', 'changes.log'));
            return response()->text($contents);
        })

        ->get('/api/hello/?:name?', [HelloController::class, 'wave'])

        ->options(':match(.*)', function () {
            return response(200)->text('');
        });
};
