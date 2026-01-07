<?php

namespace Core\Http;

use Core\Psr\Http\Message\RequestInterface;
use Core\Psr\Http\Message\StreamInterface;
use Core\Psr\Http\Message\UriInterface;

/**
 * A PSR-7 compliant representation of an outgoing request.
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class Request implements RequestInterface
{
    use MessageTrait;

    /**
     * The Request URI.
     *
     * @var \Core\Http\Uri
     */
    protected $uri;

    /**
     * The HTTP request type.
     *
     * @var string
     */
    protected $method = '';

    /**
     * Data within the path component from URI.
     *
     * @var string[]
     */
    protected $paths = [];

    /**
     * Data within the query component from URI.
     *
     * @var array
     */
    protected $query = [];

    /**
     * Post data from application/json.
     *
     * @var array
     */
    protected $json = [];

    /**
     * @param  string $method The request method
     * @param  UriInterface|mixed $uri The request URI
     * @param  array $headers The request headers
     * @param  StreamInterface|null $body The request body if any
     * @return void
     */
    public function __construct($method, $uri, $headers = [], $body = null)
    {
        $this->setHeaders($headers);
        $this->body = $body instanceof StreamInterface ? $body : null;

        $contents = $this->getBody()->getContents();
        $json = json_decode($contents, true);
        $this->json = is_array($json) ? $json : [];

        $this->method = $method;

        if (!($uri instanceof UriInterface)) {
            $uri = new Uri($uri);
            /*
            $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            $uri = $uri
                ->withScheme($scheme)
                ->withHost($_SERVER['HTTP_HOST'])
                ->withPort($_SERVER['SERVER_PORT']);
            */
        }

        $this->uri = $uri;

        $this->query = [];
        $query_params = explode('&', $this->uri->getQuery());
        foreach ($query_params as $param) {
            $keyval = explode('=', $param);
            if (count($keyval) > 1) {
                $this->query[$keyval[0]] = $keyval[1];
            } elseif (count($keyval) > 0) {
                $this->query[$keyval[0]] = null;
            }
        }

        $paths = explode('/', $this->uri->getPath());
        $this->paths = array_filter($paths, function ($value) {
            return is_string($value) && strlen(trim($value) > 0);
        });
    }

    /**
     * Retrieves the message's request target.
     *
     * Retrieves the message's request-target either as it will appear (for
     * clients), as it appeared at request (for servers), or as it was
     * specified for the instance (see withRequestTarget()).
     *
     * In most cases, this will be the origin-form of the composed URI,
     * unless a value was provided to the concrete implementation (see
     * withRequestTarget() below).
     *
     * If no URI is available, and no request-target has been specifically
     * provided, this method MUST return the string "/".
     *
     * @return string
     */
    public function getRequestTarget()
    {
        $target = $this->uri->getPath();
        if (empty($target)) {
            $target = '/';
        }
        $query = $this->uri->getQuery();
        if (strlen($query) > 0) {
            $target .= '?' . $query;
        }

        return $target;
    }

    /**
     * Returns an instance with the specific request-target.
     *
     * If the request needs a non-origin-form request-target — e.g., for
     * specifying an absolute-form, authority-form, or asterisk-form —
     * this method may be used to create an instance with the specified
     * request-target, verbatim.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * changed request target.
     *
     * @link http://tools.ietf.org/html/rfc7230#section-5.3 (for the various
     *     request-target forms allowed in request messages)
     * @param mixed $requestTarget
     * @return static
     */
    public function withRequestTarget($requestTarget)
    {
        return new static($this->getMethod(), $requestTarget, $this->getHeaders(), $this->getBody());
    }

    /**
     * Retrieves the HTTP method of the request.
     *
     * @return string Returns the request method.
     */
    public function getMethod()
    {
        return strtoupper($this->method);
    }

    /**
     * Returns an instance with the provided HTTP method.
     *
     * While HTTP method names are typically all uppercase characters, HTTP
     * method names are case-sensitive and thus implementations SHOULD NOT
     * modify the given string.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * changed request method.
     *
     * @param string $method Case-sensitive method.
     * @return static
     * @throws \InvalidArgumentException for invalid HTTP methods.
     */
    public function withMethod($method)
    {
        $clone = clone $this;
        $clone->method = $method;
        return $clone;
    }

    /**
     * Retrieves the URI instance.
     *
     * This method MUST return a UriInterface instance.
     *
     * @link http://tools.ietf.org/html/rfc3986#section-4.3
     * @return UriInterface Returns a UriInterface instance
     *     representing the URI of the request.
     */
    public function getUri()
    {
        return $this->uri;
    }

    /**
     * Gets an instance with the provided URI.
     *
     * This method MUST update the Host header of the returned request by
     * default if the URI contains a host component. If the URI does not
     * contain a host component, any pre-existing Host header MUST be carried
     * over to the returned request.
     *
     * You can opt-in to preserving the original state of the Host header by
     * setting `$preserveHost` to `true`. When `$preserveHost` is set to
     * `true`, this method interacts with the Host header in the following ways:
     *
     * - If the Host header is missing or empty, and the new URI contains
     *   a host component, this method MUST update the Host header in the returned
     *   request.
     * - If the Host header is missing or empty, and the new URI does not contain a
     *   host component, this method MUST NOT update the Host header in the returned
     *   request.
     * - If a Host header is present and non-empty, this method MUST NOT update
     *   the Host header in the returned request.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * new UriInterface instance.
     *
     * @link http://tools.ietf.org/html/rfc3986#section-4.3
     * @param UriInterface $uri New request URI to use.
     * @param bool $preserveHost Preserve the original state of the Host header.
     * @return static
     */
    public function withUri(UriInterface $uri, $preserveHost = false)
    {
        if ($preserveHost === true) {
            $target_host = $uri->getHost();
            $original_host = $this->uri->getHost();
            if (empty($original_host) && strlen($target_host) > 0) {
                return new static($this->getMethod(), $uri->withHost($target_host), $this->getHeaders(), $this->getBody());
            }
        }
        return new static($this->getMethod(), $uri, $this->getHeaders(), $this->getBody());
    }

    /**
     * Gets the path components.
     * 
     * @return string[]
     */
    public function paths()
    {
        return $this->paths;
    }

    /**
     * Gets the full path with hostname.
     * 
     * @return string
     */
    public function getFullPath()
    {
        $host = $this->uri->getHost();
        $path = $this->uri->getPath();
        return trim($host, '/') . '/' . trim($path, '/');
    }

    /**
     * Gets a validated uploaded file.
     *
     * @param string $key The file input name
     * @param array $options Validation options (allowed_types, max_size, allowed_extensions)
     * @return array|null Returns the file array if valid, null otherwise
     */
    public function file($key, $options = [])
    {
        if (!isset($_FILES[$key])) {
            return null;
        }

        $validation = validate_uploaded_file($_FILES[$key], $options);

        if ($validation === null || !$validation['valid']) {
            return null;
        }

        return $_FILES[$key];
    }

    /**
     * An associative array of variables 
     * passed to the current script via the HTTP POST method.
     * 
     * @return array
     */
    public function post()
    {
        return $_POST;
    }

    /**
     * An associative array of variables from any available source.
     * 
     * @return array
     */
    public function json()
    {
        return $this->json;
    }

    /**
     * Gets data by key from query components.
     * 
     * @param string|null $key An optional key to retrieve
     * @param string|null $default An optional default value if the given key does not exist
     * @return mixed
     */
    public function query($key = null, $default = null)
    {
        if (isset($key)) {
            return array_key_exists($key, $this->query) ? $this->query[$key] : $default;
        }

        return $this->query;
    }

    /**
     * Gets data by key from any available source.
     * 
     * @param string|null $key An optional key to retrieve
     * @param string|null $default An optional default value if the given key does not exist
     * @return mixed
     */
    public function get($key, $default = null)
    {
        if (!empty($this->json)) {
            return array_key_exists($key, $this->json) && isset($this->json[$key]) ? $this->json[$key] : $default;
        }
        return array_key_exists($key, $_POST) && isset($_POST[$key]) ? $_POST[$key] : $default;
    }

    /**
     * Gets available keys from request.
     * 
     * @return string[]
     */
    public function keys()
    {
        return !empty($this->json) ? array_keys($this->json) : array_keys($_POST);
    }

    /**
     * Gets available values from request.
     * 
     * @return array
     */
    public function values()
    {
        return !empty($this->json) ? array_values($this->json) : array_values($_POST);
    }

    /**
     * Gets all available data from request.
     * 
     * @return array
     */
    public function all()
    {
        return array_merge($this->json, $_POST);
    }
}
