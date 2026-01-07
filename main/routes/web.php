<?php

use Core\View;
use Core\Http\Request;
use Core\Routing\Router;

/**
 * web.php
 *
 * @version 1.0.0
 */

return function (Router $router) {
    $router
        ->get('/?(welcome)?', function () {
            return response()->view(new View('pages/welcome.html', ['year' => date('Y')]));
        })

        ->all(':match(.*)', function (Request $request) {
            $path = $request->getUri()->getPath();
            $method = $request->getMethod();
            if ($method === 'GET') {
                return response(404)->view(new View('pages/404.html'));
            }
            return response(404)->json(['status' => 404, 'message' => "No matching route for `[$method] $path`"]);
        });
};
