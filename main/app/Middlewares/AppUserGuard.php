<?php

namespace App\Middlewares;

use Closure;
use Core\Auth\Auth;
use Core\Http\Request;
use Core\Routing\MiddlewareInterface;
use Core\Psr\Http\Message\ResponseInterface;

/**
 * Proceeds with User token authentication from server request and returns the response accordingly.
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class AppUserGuard implements MiddlewareInterface
{
    public function process(Request $request, Closure $next): ResponseInterface
    {
        $auth = new Auth($request);
        if (!$auth->isAuth() || $auth->keyId() !== 'USER') {
            return response(401)->json(['message' => 'Unauthorized.', 'status' => 401]);
        }

        // CSRF validation with timing-safe comparison
        $csrf_from_token = $auth->csrf();
        $csrf_from_header = get_csrf_token();

        if ($csrf_from_token === null || $csrf_from_header === false || !hash_equals((string)$csrf_from_token, (string)$csrf_from_header)) {
            return response(401)->json(['message' => 'Invalid or missing CSRF token.', 'status' => 401]);
        }

        return $next($request);
    }
}
