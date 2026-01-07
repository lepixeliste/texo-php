<?php

namespace Core\Http;

use Exception;
use RuntimeException;
use Core\Psr\Http\Message\StreamInterface;

/**
 * A PSR-7 compliant data stream body
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 * @author Guzzle <http://docs.guzzlephp.org>
 */

class Stream implements StreamInterface
{
    /** @see http://php.net/manual/function.fopen.php */
    private const READABLE_MODES = '/r|a\+|ab\+|w\+|wb\+|x\+|xb\+|c\+|cb\+/';
    private const WRITABLE_MODES = '/a|w|r\+|rb\+|rw|x|c/';

    /** @var resource|null */
    protected $stream = null;
    /** @var int|null */
    protected $size = null;
    /** @var bool */
    protected $readable = false;
    /** @var bool */
    protected $writable = false;
    /** @var bool */
    protected $seekable = true;

    /**
     * @param resource|null $resource Creates a temporary file if null
     * @return void
     */
    public function __construct($resource = null)
    {
        if (!is_resource($resource)) {
            $resource = tmpfile();
        }

        $this->stream = $resource;
        $meta = stream_get_meta_data($resource);
        $this->seekable = $meta['seekable'];
        $this->readable = (bool)preg_match(self::READABLE_MODES, $meta['mode']);
        $this->writable = (bool)preg_match(self::WRITABLE_MODES, $meta['mode']);
    }

    /**
     * Closes the stream when the destructed.
     * 
     * @return void
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * Reads all data from the stream into a string, from the beginning to end.
     *
     * This method MUST attempt to seek to the beginning of the stream before
     * reading data and read the stream until the end is reached.
     *
     * Warning: This could attempt to load a large amount of data into memory.
     *
     * This method MUST NOT raise an exception in order to conform with PHP's
     * string casting operations.
     *
     * @see http://php.net/manual/en/language.oop5.magic.php#object.tostring
     * @return string
     */
    public function __toString()
    {
        try {
            if ($this->isSeekable()) {
                $this->seek(0);
            }
            return $this->getContents();
        } catch (Exception $e) {
            return '';
        }
    }

    /**
     * Closes the stream and any underlying resources.
     *
     * @return void
     */
    public function close()
    {
        if (!isset($this->stream) || !is_resource($this->stream)) return;
        fclose($this->stream);
    }

    /**
     * Separates any underlying resources from the stream.
     *
     * After the stream has been detached, the stream is in an unusable state.
     *
     * @return resource|null Underlying PHP stream, if any
     */
    public function detach()
    {
        if (!isset($this->stream) || !is_resource($this->stream)) return null;

        $result = $this->stream;
        unset($this->stream);
        return $result;
    }

    /**
     * Gets the size of the stream if known.
     *
     * @return int|null Returns the size in bytes if known, or null if unknown.
     */
    public function getSize()
    {
        if (!isset($this->stream) || !is_resource($this->stream)) return null;

        if (!isset($this->size)) {
            return $this->size;
        }

        $stats = fstat($this->stream);
        if (is_array($stats) && isset($stats['size'])) {
            $this->size = $stats['size'];
            return $this->size;
        }
        return null;
    }

    /**
     * Gets the current position of the file read/write pointer.
     *
     * @return int Position of the file pointer
     * @throws \RuntimeException on error.
     */
    public function tell()
    {
        if (!isset($this->stream)) {
            throw new RuntimeException('Stream is detached');
        }

        $result = ftell($this->stream);
        if ($result === false) {
            throw new RuntimeException('Unable to determine stream position');
        }

        return $result;
    }

    /**
     * Gets true if the stream is at the end of the stream.
     *
     * @return bool
     */
    public function eof()
    {
        if (!isset($this->stream)) {
            throw new RuntimeException('Stream is detached');
        }

        return feof($this->stream);
    }

    /**
     * Gets whether or not the stream is seekable.
     *
     * @return bool
     */
    public function isSeekable()
    {
        return $this->seekable;
    }

    /**
     * Seek to a position in the stream.
     *
     * @link http://www.php.net/manual/en/function.fseek.php
     * @param int $offset Stream offset
     * @param int $whence Specifies how the cursor position will be calculated
     *     based on the seek offset. Valid values are identical to the built-in
     *     PHP $whence values for `fseek()`.  SEEK_SET: Set position equal to
     *     offset bytes SEEK_CUR: Set position to current location plus offset
     *     SEEK_END: Set position to end-of-stream plus offset.
     * @throws \RuntimeException on failure.
     */
    public function seek($offset, $whence = SEEK_SET)
    {
        $whence = is_numeric($whence) ? $whence : 0;

        if (!isset($this->stream)) {
            throw new RuntimeException('Stream is detached');
        }
        if (!$this->seekable) {
            throw new RuntimeException('Stream is not seekable');
        }

        if (fseek($this->stream, $offset, $whence) === -1) {
            throw new RuntimeException('Unable to seek to stream position ' . $offset . ' with whence ' . var_export($whence, true));
        }
    }

    /**
     * Seek to the beginning of the stream.
     *
     * If the stream is not seekable, this method will raise an exception;
     * otherwise, it will perform a seek(0).
     *
     * @see seek()
     * @link http://www.php.net/manual/en/function.fseek.php
     * @throws \RuntimeException on failure.
     */
    public function rewind()
    {
        $this->seek(0);
    }

    /**
     * Gets whether or not the stream is writable.
     *
     * @return bool
     */
    public function isWritable()
    {
        return $this->writable;
    }

    /**
     * Write data to the stream.
     *
     * @param string $string The string that is to be written.
     * @return int Returns the number of bytes written to the stream.
     * @throws \RuntimeException on failure.
     */
    public function write($string)
    {
        if (!isset($this->stream)) {
            throw new RuntimeException('Stream is detached');
        }
        if (!$this->writable) {
            throw new RuntimeException('Cannot write to a non-writable stream');
        }

        // We can't know the size after writing anything
        $this->size = null;
        $result = fwrite($this->stream, $string);

        if ($result === false) {
            throw new RuntimeException('Unable to write to stream');
        }

        return $result;
    }

    /**
     * Gets whether or not the stream is readable.
     *
     * @return bool
     */
    public function isReadable()
    {
        return $this->readable;
    }

    /**
     * Read data from the stream.
     *
     * @param int $length Read up to $length bytes from the object and return
     *     them. Fewer than $length bytes may be returned if underlying stream
     *     call returns fewer bytes.
     * @return string Returns the data read from the stream, or an empty string
     *     if no bytes are available.
     * @throws \RuntimeException if an error occurs.
     */
    public function read($length)
    {
        if (!isset($this->stream)) {
            throw new RuntimeException('Stream is detached');
        }
        if (!$this->readable) {
            throw new RuntimeException('Cannot read from non-readable stream');
        }
        if ($length < 0) {
            throw new RuntimeException('Length parameter cannot be negative');
        }

        if (0 === $length) {
            return '';
        }

        try {
            $string = fread($this->stream, $length);
        } catch (Exception $e) {
            throw new RuntimeException('Unable to read from stream', 0, $e);
        }

        if (false === $string) {
            throw new RuntimeException('Unable to read from stream');
        }

        return $string;
    }

    /**
     * Gets the remaining contents in a string
     *
     * @return string
     * @throws \RuntimeException if unable to read or an error occurs while
     *     reading.
     */
    public function getContents()
    {
        if (!isset($this->stream)) {
            throw new RuntimeException('Stream is detached');
        }

        if (!$this->readable) {
            throw new RuntimeException('Cannot read from non-readable stream');
        }

        try {
            $this->rewind();
            $contents = stream_get_contents($this->stream);
            if ($contents === false) {
                throw new RuntimeException('Unable to read stream contents');
            }
            return $contents;
        } catch (Exception $e) {
            throw new RuntimeException(sprintf(
                'Unable to read stream contents: %s',
                $e->getMessage()
            ), 0, $e);
        }
    }

    /**
     * Gets stream metadata as an associative array or retrieve a specific key.
     *
     * The keys returned are identical to the keys returned from PHP's
     * stream_get_meta_data() function.
     *
     * @link http://php.net/manual/en/function.stream-get-meta-data.php
     * @param string $key Specific metadata to retrieve.
     * @return array|mixed|null Returns an associative array if no key is
     *     provided. Returns a specific key value if a key is provided and the
     *     value is found, or null if the key is not found.
     */
    public function getMetadata($key = null)
    {
        if (!isset($this->stream)) {
            return isset($key) ? null : [];
        }

        $meta = stream_get_meta_data($this->stream);
        if (isset($key)) {
            return array_key_exists($key, $meta) ? $meta[$key] : null;
        }

        return $meta;
    }
}
