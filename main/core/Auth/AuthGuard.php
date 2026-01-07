<?php

namespace Core\Auth;

use Closure;
use Core\Http\Request;
use Core\Routing\MiddlewareInterface;
use Core\Psr\Http\Message\ResponseInterface;

/**
 * Proceeds with authentication from server request and returns the response accordingly.
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class AuthGuard implements MiddlewareInterface
{
    /**
     * Process an incoming server request and return a response, optionally delegating
     * to the next middleware component to create the response.
     *
     * @param Request $request
     * @param Closure $next
     * @return ResponseInterface
     */
    public function process(Request $request, Closure $next): ResponseInterface
    {
        $auth = new Auth($request);
        if (!$auth->isAuth()) {
            return response(401)->json(['message' => 'Unauthorized.', 'status' => 401]);
        }
        return $next($request);
    }
}
