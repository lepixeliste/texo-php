<?php

namespace Core\Http;

use Core\Psr\Http\Message\RequestFactoryInterface;
use Core\Psr\Http\Message\RequestInterface;
use Core\Psr\Http\Message\ResponseFactoryInterface;
use Core\Psr\Http\Message\ResponseInterface;
use Core\Psr\Http\Message\StreamInterface;
use Core\Psr\Http\Message\UriFactoryInterface;
use Core\Psr\Http\Message\UriInterface;

/**
 * PSR-17 compliant HTTP factory message
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class HttpFactory implements RequestFactoryInterface, ResponseFactoryInterface, UriFactoryInterface
{
    /**
     * Create a new request.
     *
     * @param string $method The HTTP method associated with the request.
     * @param UriInterface|string $uri The URI associated with the request. If
     *     the value is a string, the factory MUST create a UriInterface
     *     instance based on it.
     * @return RequestInterface
     */
    public function createRequest(string $method, $uri): RequestInterface
    {
        return new Request($method, $uri);
    }

    /**
     * Create a new response.
     *
     * @param int $code HTTP status code; defaults to 200
     * @param string $reasonPhrase Reason phrase to associate with status code
     *     in generated response; if none is provided implementations MAY use
     *     the defaults as suggested in the HTTP specification.
     * @return ResponseInterface
     */
    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        return new Response($code, $reasonPhrase);
    }

    /**
     * Create a new stream from a string.
     *
     * The stream SHOULD be created with a temporary resource.
     *
     * @param string $content String content with which to populate the stream.
     * @return StreamInterface
     */
    public function createStream(string $content = ''): StreamInterface
    {
        $res = fopen('php://temp', 'r+');
        fwrite($res, $content);
        return new Stream($res);
    }

    /**
     * Create a stream from an existing file.
     *
     * The file MUST be opened using the given mode, which may be any mode
     * supported by the `fopen` function.
     *
     * The `$filename` MAY be any string supported by `fopen()`.
     *
     * @param string $filename Filename or stream URI to use as basis of stream.
     * @param string $mode Mode with which to open the underlying filename/stream.
     * @return StreamInterface
     * @throws \RuntimeException If the file cannot be opened.
     * @throws \InvalidArgumentException If the mode is invalid.
     */
    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
    {
        $res = fopen($filename, $mode);
        if (!$res) {
            throw new \RuntimeException(sprintf('The file `%s` cannot be opened', $filename));
        }
        return new Stream($res);
    }

    /**
     * Create a new stream from an existing resource.
     *
     * The stream MUST be readable and may be writable.
     *
     * @param resource $resource PHP resource to use as basis of stream.
     * @return StreamInterface
     */
    public function createStreamFromResource($resource): StreamInterface
    {
        return new Stream($resource);
    }

    /**
     * Create a new URI.
     *
     * @param string $uri
     * @return UriInterface
     * @throws \InvalidArgumentException If the given URI cannot be parsed.
     */
    public function createUri(string $uri = ''): UriInterface
    {
        return new Uri($uri);
    }
}
