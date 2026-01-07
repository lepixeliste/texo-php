<?php

namespace Core\Routing;

use Core\Collection;
use Core\Psr\Http\Message\ResponseInterface;

/**
 * Handles response type conversion.
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class ResponseHandler
{
    /**
     * Normalizes a route response into a ResponseInterface.
     *
     * @param  mixed $response
     * @return ResponseInterface
     * @throws \Core\Routing\RouteException
     */
    public static function normalize($response)
    {
        if ($response instanceof ResponseInterface) {
            return $response;
        }

        if (is_string($response)) {
            return response()->text($response);
        }

        if (is_array($response)) {
            return response()->json($response);
        }

        if ($response instanceof Collection) {
            return response()->json($response->all());
        }

        throw new RouteException('Response type is not valid. Expected ResponseInterface, string, array, or Collection.');
    }
}
