<?php

namespace Core\Http;

use InvalidArgumentException;
use JsonSerializable;
use Core\View;
use Core\Psr\Http\Message\ResponseInterface;

/**
 * A PSR-7 compliant representation of an outgoing response.
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class Response implements ResponseInterface
{
    use MessageTrait;

    /**
     * HTTP MIME Content-Type.
     *
     * @var string
     */
    protected $contentType = '';

    /**
     * HTTP response code.
     *
     * @var int
     */
    protected $httpCode = 200;

    /**
     * HTTP response reason phrase.
     *
     * @var string
     */
    protected $message = '';

    /**
     * Response type.
     *
     * @var string
     */
    protected $type = 'json';

    /**
     * Response data.
     *
     * @var mixed
     */
    protected $data;

    /**
     * Response attachment object.
     *
     * @var \Core\Http\Attachment
     */
    protected $attachment;

    /**
     * Response location redirect.
     *
     * @var string
     */
    protected $location;

    /**
     * @param  int $code Any HTTP status code
     * @param  string message An optional status message
     * @return void
     */
    public function __construct(int $code = 200, string $message = '')
    {
        $this->contentType = sprintf('Content-Type: text/plain; charset=%s', getenv('APP_CHARSET'));
        $this->type = 'text';
        $this->httpCode = $this->assertCode($code);
        $this->message = $message;
    }

    /**
     * Gets the response status code.
     *
     * The status code is a 3-digit integer result code of the server's attempt
     * to understand and satisfy the request.
     *
     * @return int Status code.
     */
    public function getStatusCode()
    {
        return $this->httpCode;
    }

    /**
     * Return an instance with the specified status code and, optionally, reason phrase.
     *
     * If no reason phrase is specified, implementations MAY choose to default
     * to the RFC 7231 or IANA recommended reason phrase for the response's
     * status code.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * updated status and reason phrase.
     *
     * @link http://tools.ietf.org/html/rfc7231#section-6
     * @link http://www.iana.org/assignments/http-status-codes/http-status-codes.xhtml
     * @param int $code The 3-digit integer result code to set.
     * @param string $reasonPhrase The reason phrase to use with the
     *     provided status code; if none is provided, implementations MAY
     *     use the defaults as suggested in the HTTP specification.
     * @return static
     * @throws \InvalidArgumentException For invalid status code arguments.
     */
    public function withStatus($code, $reasonPhrase = '')
    {
        $clone = clone $this;
        $clone->httpCode = $this->assertCode($code);
        $clone->message = $reasonPhrase;
        return $clone;
    }

    /**
     * Gets the response reason phrase associated with the status code.
     *
     * Because a reason phrase is not a required element in a response
     * status line, the reason phrase value MAY be null. Implementations MAY
     * choose to return the default RFC 7231 recommended reason phrase (or those
     * listed in the IANA HTTP Status Code Registry) for the response's
     * status code.
     *
     * @link http://tools.ietf.org/html/rfc7231#section-6
     * @link http://www.iana.org/assignments/http-status-codes/http-status-codes.xhtml
     * @return string Reason phrase; must return an empty string if none present.
     */
    public function getReasonPhrase()
    {
        $message = $this->message;
        if (!is_string($message) || empty($message)) {
            $status = $this->getStatusCode();
            switch ($status) {
                case 100: {
                        $message = 'Continue';
                        break;
                    }
                case 200: {
                        $message = 'OK';
                        break;
                    }
                case 201: {
                        $message = 'Created';
                        break;
                    }
                case 202: {
                        $message = 'Accepted';
                        break;
                    }
                case 204: {
                        $message = 'No Content';
                        break;
                    }
                case 301: {
                        $message = 'Moved Permanently';
                        break;
                    }
                case 302: {
                        $message = 'Found';
                        break;
                    }
                case 307: {
                        $message = 'Temporary Redirect';
                        break;
                    }
                case 308: {
                        $message = 'Permanent Redirect';
                        break;
                    }
                case 400: {
                        $message = 'Bad Request';
                        break;
                    }
                case 401: {
                        $message = 'Unauthorized';
                        break;
                    }
                case 402: {
                        $message = 'Payment Required';
                        break;
                    }
                case 403: {
                        $message = 'Forbidden';
                        break;
                    }
                case 404: {
                        $message = 'Not found';
                        break;
                    }
                case 405: {
                        $message = 'Method Not Allowed';
                        break;
                    }
                case 406: {
                        $message = 'Not Acceptable';
                        break;
                    }
                case 408: {
                        $message = 'Request Timeout';
                        break;
                    }
                case 418: {
                        $message = "I'm a teapot";
                        break;
                    }
                case 500: {
                        $message = 'Internal Server Error';
                        break;
                    }
                case 501: {
                        $message = 'Not Implemented';
                        break;
                    }
                case 502: {
                        $message = 'Bad Gateway';
                        break;
                    }
                case 503: {
                        $message = 'Service Unavailable';
                        break;
                    }
                default: {
                        $message = 'Unassigned';
                    }
            }
        }
        return implode(' ', [$this->httpCode, $message]);
    }

    /**
     * Returns an instance with the provided JSON.
     * 
     * @param  JsonSerializable|array $data
     * @return static
     */
    public function json($data)
    {
        $clone = clone $this;
        $clone->contentType = sprintf('Content-Type: application/json; charset=%s', getenv('APP_CHARSET'));
        $clone->type = 'json';
        if (is_array($data)) {
            $clone->data = $data;
        } elseif ($data instanceof JsonSerializable) {
            $clone->data = $data->jsonSerialize();
        }
        return $clone;
    }

    /**
     * Returns an instance with the provided text.
     * 
     * @param  string $data
     * @param  string $type An optional MIME Content-type, text/plain by default
     * @return static
     */
    public function text($data, $type = 'text/plain')
    {
        $clone = clone $this;
        $clone->contentType = sprintf('Content-Type: %s; charset=%s', $type, getenv('APP_CHARSET'));
        $clone->type = 'text';
        if (is_string($data)) {
            $clone->data = $data;
        }
        return $clone;
    }

    /**
     * Returns an instance with the provided HTML content.
     * 
     * @param  string $data
     * @return static
     */
    public function html($data)
    {
        $clone = clone $this;
        $clone->contentType = sprintf('Content-Type: text/html; charset=%s', getenv('APP_CHARSET'));
        $clone->type = 'html';
        if (is_string($data)) {
            $clone->data = $data;
        }
        return $clone;
    }

    /**
     * Returns an instance with the provided View.
     * 
     * @param  \Core\View $view
     * @return static
     * @throws \InvalidArgumentException
     */
    public function view(View $view)
    {
        if (!($view instanceof View)) {
            throw new InvalidArgumentException('View is not valid.');
        }
        $clone = clone $this;
        $clone->contentType = sprintf('Content-Type: text/html; charset=%s', getenv('APP_CHARSET'));
        $clone->type = 'html';
        $clone->data = $view->render();
        return $clone;
    }

    /**
     * Returns an instance with the provided streamable data.
     * 
     * @param  \JsonSerializable|array|object $data
     * @param  string $filename
     * @return static
     */
    public function stream($data, $filename)
    {
        $clone = clone $this;
        $clone->contentType = sprintf('Content-Type: application/octet-stream; filename=%s', $filename);
        $clone->type = 'stream';
        $clone->data = is_array($data) || (is_object($data) && $data instanceof JsonSerializable) ?
            json_encode($data)
            : (is_string($data) ? $data : '');
        return $clone;
    }

    /**
     * Returns an instance with the provided file.
     * 
     * @param  string $filepath The file path
     * @return static
     */
    public function transfer($filepath)
    {
        $attachment = new Attachment($filepath);
        $clone = clone $this;
        $clone->contentType = sprintf('Content-Type: %s;', $attachment->contentType());
        $clone->type = 'transfer';
        $clone->attachment = $attachment;
        return $clone;
    }

    /**
     * Returns an instance with the new location for redirection.
     * 
     * @param  string $location The new URI path
     * @return static
     */
    public function redirect($location)
    {
        $clone = clone $this;
        if (!in_array($clone->httpCode, [301, 302, 307, 308])) {
            $clone->httpCode = 302;
        }
        $clone->contentType = sprintf('Content-Type: text/plain; charset=%s', getenv('APP_CHARSET'));
        $clone->type = 'redirect';
        $clone->location = $location;
        return $clone;
    }

    /**
     * Returns an instance to skip the router checks.
     * 
     * @return static
     */
    public function continue()
    {
        $new = new static(100);
        $new->type = 'continue';
        return $new;
    }

    /**
     * Gets the location for redirection, if any.
     * 
     * @return string|null
     */
    public function location()
    {
        return $this->location;
    }

    /**
     * Gets the MIME content-type.
     * 
     * @return string
     */
    public function contentType()
    {
        return $this->contentType;
    }

    /**
     * Gets the response type.
     * 
     * @return string
     */
    public function responseType()
    {
        return $this->type;
    }

    /**
     * Checks if the response was successful.
     * 
     * @return bool
     */
    public function ok()
    {
        return $this->httpCode > 199 && $this->httpCode < 300;
    }

    /**
     * Gets the current data, if any.
     * 
     * @return mixed|null
     */
    public function data()
    {
        return $this->data;
    }

    /**
     * Gets the current attachment, if any.
     * 
     * @return \Core\Http\Attachment|null
     */
    public function attachment()
    {
        return $this->attachment;
    }

    /**
     * Returns an instance for chunked transfer-encoding.
     * @return static
     */
    public function chunked($type = 'text/plain')
    {
        $clone = clone $this;
        $clone->contentType = sprintf('Content-Type: %s; charset=%s', $type, getenv('APP_CHARSET'));
        $clone->type = 'chunked';
        $clone->data = '';
        return $clone;
    }

    /**
     * Sends chunk of data.
     * 
     * @param string $data
     */
    public function sendChunk(string $data)
    {
        echo dechex(strlen($data)) . "\r\n";
        echo $data . "\r\n";
        ob_flush();
        flush();
    }

    /**
     * Sends chunk of data.
     * 
     * @param string $data
     */
    public function endChunk()
    {
        $this->sendChunk('');
    }

    /**
     * Asserts the HTTP status code is valid.
     * 
     * @return int Status code.
     */
    protected function assertCode($code)
    {
        if (!is_numeric($code) || $code < 100 || $code > 599) {
            return 418;
        }
        return $code;
    }
}
