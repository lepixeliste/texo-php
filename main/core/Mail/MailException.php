<?php

namespace Core\Mail;

use Exception;

/**
 * Exception for any mailing errors.
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class MailException extends Exception
{
    /**
     * Sets the file in which the exception was created.
     * 
     * @param  string $file The filename
     * @return self
     */
    public function setFile($file)
    {
        $this->file = is_string($file) ? $file : '';
        return $this;
    }

    /**
     * Sets the line in which the exception was created.
     * 
     * @param  int $line
     * @return self
     */
    public function setLine($line)
    {
        $this->line = intval($line);
        return $this;
    }
}
