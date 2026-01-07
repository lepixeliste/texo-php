<?php

namespace Core\Auth;

use Exception;

/**
 * Exception for any token-related errors.
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class JWTException extends Exception
{
    /** When token signature is invalid. */
    const SIGNATURE_INVALID   = 1;
    /** When the nbf key is not defined. */
    const BEFORE_VALIDATION   = 2;
    /** When token is expired. */
    const EXPIRED_TOKEN       = 3;
    /** When token is not valid. */
    const INVALID_TOKEN       = 4;

    /**
     * @param int $code The Exception code
     * @param \Throwable|null $previous The previous exception used for the exception chaining
     * @return void
     */
    public function __construct($code, $previous = null)
    {
        $message = '';
        switch ($code) {
            case static::SIGNATURE_INVALID:
                $message = 'Invalid signature.';
                break;
            case static::BEFORE_VALIDATION:
                $message = 'Token not eligible.';
                break;
            case static::EXPIRED_TOKEN:
                $message = 'Token expired.';
                break;
            case static::INVALID_TOKEN:
                $message = 'Invalid token.';
                break;
            default:
                $message = 'Unknown exception.';
                break;
        }

        parent::__construct($message, $code, $previous);
    }
}
