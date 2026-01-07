<?php

namespace Core\Http;

use Core\Http\Request;
use Core\Psr\Http\Client\ClientException;
use Core\Psr\Http\Client\ClientInterface;
use Core\Psr\Http\Message\RequestInterface;
use Core\Psr\Http\Message\ResponseInterface;
use Core\Psr\Http\Message\StreamInterface;

/**
 * A PSR-18 compliant HTTP client 
 * to send PSR-7-compatible HTTP Request messages 
 * and return a PSR-7-compatible HTTP Response message.
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class HttpClient implements ClientInterface
{
    /**
     * The Client connection timeout request.
     *
     * @var int
     */
    public $timeout = 60;

    /**
     * Force to use the cache instead of a new connection.
     *
     * @var bool
     */
    public $cache = true;

    /**
     * Sends a PSR-7 request and returns a PSR-7 response.
     *
     * @param RequestInterface $request
     * @return ResponseInterface
     * @throws \Core\Psr\Http\Client\ClientExceptionInterface If an error happens while processing the request.
     */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $headers = $request->getHeaders();
        $method = $request->getMethod();
        $uri = $request->getUri();
        $url = strval($uri);
        $ch = curl_init($url);
        if (!$ch) {
            throw new ClientException("Could not initialize `[$method] $url`");
        }

        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, $this->cache);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        switch ($method) {
            case 'PUT':
            case 'POST': {
                    curl_setopt($ch, $method === 'PUT' ? CURLOPT_PUT : CURLOPT_POST, true);
                    /*
                    if (isset($this->attachment) && file_exists($this->attachment)) {
                        $filehandle = @file_get_contents($this->attachment);
                        curl_setopt($ch, CURLOPT_INFILE, $filehandle);
                        curl_setopt($ch, CURLOPT_INFILESIZE, filesize($this->attachment));
                        $headers = $request->withHeader('Content-Type', 'multipart/form-data')->getHeaders();
                    } else {
                        $headers = $request->withHeader('Content-Type', 'application/json')->getHeaders();
                    }
                    */
                    $contents = $request->getBody()->getContents();
                    if (!empty($contents)) {
                        $headers = $request->withHeader('Content-Type', 'application/json')->getHeaders();
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $contents);
                    }
                    break;
                }
            case 'DELETE': {
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                    break;
                }
            default:
                break;
        }

        if (count($headers) > 0) {
            $httpheader = [];
            foreach ($headers as $n => $v) {
                $httpheader[] = $n . ': ' . (is_array($v) ? implode(', ', $v) : $v);
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $httpheader);
        }

        $data = curl_exec($ch);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $status_code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

        curl_close($ch);

        switch ($content_type) {
            case 'application/octet-stream': {
                    preg_match('/;\s?filename=(.*)\s?/mi', is_string($data) ? $data : '', $matches);
                    $filename = count($matches) > 1 ? $matches[1] : uniqid();
                    $res_body = is_string($data) && is_numeric($header_size) ? substr($data, $header_size) : '';
                    return response($status_code)->json(['filename' => $filename, 'content' => $res_body]);
                }
            default: {
                    if (is_string($data)) {
                        $res_body = is_numeric($header_size) ? substr($data, $header_size) : '';
                        $json_body = json_decode($res_body, true);
                        return response($status_code)->json($json_body);
                    }
                }
        }


        return response($status_code)->json(['message' => "Could not fetch `[$method] $url`"]);
    }

    /**
     * Sends a GET Request to any valid URL.
     *
     * @param mixed $url
     * @param array $headers Optional headers
     * @return ResponseInterface
     * @throws \Core\Psr\Http\Client\ClientExceptionInterface If an error happens while processing the request.
     */
    public static function get($url, $headers = [])
    {
        $request = new Request('GET', $url, $headers);
        $client = new static();
        return $client->sendRequest($request);
    }

    /**
     * Sends a POST Request with data to any valid URL.
     *
     * @param mixed $url
     * @param StreamInterface $data
     * @param array $headers Optional headers
     * @return ResponseInterface
     * @throws \Core\Psr\Http\Client\ClientExceptionInterface If an error happens while processing the request.
     */
    public static function post($url, StreamInterface $data, $headers = [])
    {
        $request = new Request('POST', $url, $headers, $data);
        $client = new static();
        return $client->sendRequest($request);
    }

    /**
     * Sends a PUT Request with data to any valid URL.
     *
     * @param mixed $url
     * @param StreamInterface $data
     * @param array $headers Optional headers
     * @return ResponseInterface
     * @throws \Core\Psr\Http\Client\ClientExceptionInterface If an error happens while processing the request.
     */
    public static function put($url, StreamInterface $data, $headers = [])
    {
        $request = new Request('PUT', $url, $headers, $data);
        $client = new static();
        return $client->sendRequest($request);
    }

    /**
     * Sends a DELETE Request to any valid URL.
     *
     * @param mixed $url
     * @param array $headers Optional headers
     * @return ResponseInterface
     * @throws \Core\Psr\Http\Client\ClientExceptionInterface If an error happens while processing the request.
     */
    public static function delete($url, $headers = [])
    {
        $request = new Request('DELETE', $url, $headers);
        $client = new static();
        return $client->sendRequest($request);
    }
}
