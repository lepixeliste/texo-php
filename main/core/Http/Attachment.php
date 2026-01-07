<?php

namespace Core\Http;

use InvalidArgumentException;

/**
 * A convenient wrapper to handle attachment file.
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class Attachment
{
    /** @var string */
    public $filename;

    /** @var string */
    public $filepath;

    /**
     * @param string $filepath
     * @return void
     */
    public function __construct($filepath)
    {
        if (!file_exists($filepath)) {
            throw new InvalidArgumentException("`$filepath` does not exist.");
        }
        $this->filename = basename($filepath);
        $this->filepath = $filepath;
    }

    /**
     * Gets MIME Content-type of the file.
     * 
     * @return string
     */
    public function contentType()
    {
        $m = mime_content_type($this->filepath);
        return $m !== false ? $m : 'application/octet-stream';
    }

    /**
     * Gets the file size.
     * 
     * @return int
     */
    public function filesize()
    {
        $s = filesize($this->filepath);
        return $s !== false ? $s : 0;
    }
}
