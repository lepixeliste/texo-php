<?php

namespace Core\Pdo;

use Exception;

/**
 * Exception for any model-related errors.
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class ModelException extends Exception
{
    /** No table has been selected. */
    const NO_TABLE = 1;
    /** Current underlying model is not a valid object. */
    const INVALID_OBJECT = 2;
    /** No valid relationship. */
    const NO_RELATION = 3;

    /**
     * @param int $code The Exception code.
     * @param null|Throwable $previous The previous exception used for the exception chaining
     * @return void
     */
    public function __construct($code, $arg = '', $previous = null)
    {
        $message = '';
        switch ($code) {
            case static::NO_TABLE:
                $message = 'No table selected.';
                break;
            case static::INVALID_OBJECT:
                $message = 'Instance is not a valid object.';
                break;
            case static::NO_RELATION:
                $message = "Relation `$arg` does not exist.";
                break;
            default:
                $message = 'Unknown exception.';
                break;
        }

        parent::__construct($message, $code, $previous);
    }
}
