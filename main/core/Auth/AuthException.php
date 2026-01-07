<?php

namespace Core\Auth;

use Exception;

/**
 * Exception for any authentication errors.
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class AuthException extends Exception
{
    /** When the authentication failed */
    const AUTH_FAILED     = 1;
    /** When the user is invalid */
    const INVALID_USER    = 2;
    /** When the password is invalid */
    const INVALID_PASS    = 3;

    /**
     * @param int $code The Exception code.
     * @param null|Throwable $previous The previous exception used for the exception chaining
     * @return void
     */
    public function __construct($code, $previous = null)
    {
        $message = '';
        switch ($code) {
            case static::AUTH_FAILED:
                $message = 'Authorization failed';
                break;
            case static::INVALID_USER:
                $message = 'Invalid user';
                break;
            case static::INVALID_PASS:
                $message = 'Invalid password';
                break;
            default:
                $message = 'Unknown exception';
                break;
        }

        parent::__construct($message, $code, $previous);
    }
}
