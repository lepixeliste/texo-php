<?php

namespace Core;

use Exception;

/**
 * Exception for any controller-related errors.
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class ControllerException extends Exception
{
    /** When no valid \Core\Context is found. */
    const NO_CONTEXT = 1;

    /**
     * @param int $code The Exception code
     * @param \Throwable|null $previous The previous exception used for the exception chaining
     * @return void
     */
    public function __construct($code, $previous = null)
    {
        $message = '';
        switch ($code) {
            case static::NO_CONTEXT:
                $message = 'No service context available.';
                break;
            default:
                $message = 'Unknown exception.';
                break;
        }

        parent::__construct($message, $code, $previous);
    }
}
