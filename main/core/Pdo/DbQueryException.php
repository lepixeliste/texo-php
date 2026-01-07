<?php

namespace Core\Pdo;

use Exception;

/**
 * Exception for any query-related errors.
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class DbQueryException extends Exception
{
    /** No table has been found. */
    const NO_TABLE = 1;
    /** Query is empty. */
    const EMPTY_QUERY = 2;
    /** Query is invalid. */
    const INVALID_QUERY = 3;
    /** Command is invalid. */
    const INVALID_COMMAND = 4;
    /** No valid arguments. */
    const INVALID_ARGUMENTS = 5;

    /**
     * @param int $code The Exception code.
     * @param null|Throwable $previous The previous exception used for the exception chaining
     * @return void
     */
    public function __construct($code, $previous = null)
    {
        $message = '';
        switch ($code) {
            case static::NO_TABLE:
                $message = 'No table selected.';
                break;
            case static::EMPTY_QUERY:
                $message = 'Query is empty.';
                break;
            case static::INVALID_QUERY:
                $message = 'Invalid query.';
                break;
            case static::INVALID_COMMAND:
                $message = 'Invalid command.';
                break;
            case static::INVALID_ARGUMENTS:
                $message = 'Invalid arguments.';
                break;
            default:
                $message = 'Unknown exception.';
                break;
        }

        parent::__construct($message, $code, $previous);
    }
}
