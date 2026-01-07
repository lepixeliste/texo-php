<?php

namespace Core\Routing;

use Closure;
use Core\Http\Request;
use Core\Psr\Http\Message\ResponseInterface;

/**
 * Interface in processing a server request and response.
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

interface MiddlewareInterface
{
    /**
     * Filters an incoming server request and returns a response, optionally delegating
     * to the next middleware component to create the response.
     *
     * @param Request $request
     * @param Closure $next
     * @return ResponseInterface
     */
    public function process(Request $request, Closure $next): ResponseInterface;
}
