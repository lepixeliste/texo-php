<?php

namespace Core\Auth;

use Exception;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * JSON Web Token implementation, based on this spec:
 * https://tools.ietf.org/html/rfc7519
 *
 * @category Authentication
 * @author   Neuman Vong <neuman@twilio.com>
 * @author   Anant Narayanan <anant@php.net>
 * @author   Charlie LEDUC <contact@pixeliste.fr>
 * @license  http://opensource.org/licenses/BSD-3-Clause 3-clause BSD
 * @link     https://github.com/firebase/php-jwt
 */

class JWT
{
    /**
     * When checking nbf, iat or expiration times,
     * we want to provide some extra leeway time to
     * account for clock skew.
     */
    public static $leeway = 0;

    /** @var array<string,array> */
    protected static $supported_algs = [
        'HS256' => ['hash_hmac', 'SHA256'],
        'HS512' => ['hash_hmac', 'SHA512'],
        'HS384' => ['hash_hmac', 'SHA384'],
        'RS256' => ['openssl', 'SHA256'],
        'RS384' => ['openssl', 'SHA384'],
        'RS512' => ['openssl', 'SHA512'],
    ];

    /** @var array<string,string> */
    protected static $keys = [];

    /** @var string */
    protected $token = '';

    /** @var object|null */
    protected $header;

    /** @var object|null */
    protected $payload;

    /** @var string|false */
    protected $signature = false;

    /** @var string */
    protected $signingInput = '';

    /**
     * @param string|null $jwt The token string
     * @return void
     */
    public function __construct($jwt = null)
    {
        if (!is_string($jwt)) {
            return;
        }

        $this->token = $jwt;
        $tks = explode('.', $jwt);
        if (count($tks) !== 3) {
            throw new UnexpectedValueException('Wrong number of segments');
        }

        list($headb64, $bodyb64, $cryptob64) = $tks;
        if (null === ($this->header = $this->jsonDecode($this->urlsafeB64Decode($headb64)))) {
            throw new UnexpectedValueException('Invalid header encoding');
        }
        if (null === ($this->payload = $this->jsonDecode($this->urlsafeB64Decode($bodyb64)))) {
            throw new UnexpectedValueException('Invalid payload encoding');
        }
        if (false === ($this->signature = $this->urlsafeB64Decode($cryptob64))) {
            throw new UnexpectedValueException('Invalid signature encoding');
        }
        $this->signingInput = implode('.', [$headb64, $bodyb64]);
    }

    /**
     * The token key Id, if any.
     *
     * @return string|null
     */
    public function kid()
    {
        return isset($this->header, $this->header->kid) ? $this->header->kid : null;
    }

    /**
     * The signing algorithm.
     *
     * @return string
     */
    public function algorithm()
    {
        return isset($this->header, $this->header->alg) ? $this->header->alg : 'HS256';
    }

    /**
     * The decoded payload into a PHP object, if any.
     *
     * @return object|null
     */
    public function payload()
    {
        return $this->payload;
    }

    /**
     * The token signature, if any.
     *
     * @return string|false
     */
    public function signature()
    {
        return $this->signature;
    }

    /**
     * Gets the signing key by Id.
     * 
     * @param string|null $id
     * @param string $algo
     * @return OpenSSLAsymmetricKey|string
     */
    protected function signingKey($id = null, $algo = 'HS256', $is_private = true)
    {
        if (empty(static::$keys)) {
            JWT::loadKeysFromEnv();
        }

        list($function, $algo) = static::$supported_algs[$algo];
        switch ($function) {
            case 'openssl': {
                    $ssl = new SSL('jwt');
                    $asym_key = $is_private ? $ssl->getPrivate() : $ssl->getPublic();
                    return !$asym_key ? '' : $asym_key;
                }
            default: {
                    $key_id = isset($id) ? $id : 'USER';
                    $keys = array_keys(static::$keys);
                    $default_key = count($keys) > 0 ? static::$keys[$keys[0]] : '';
                    return isset(static::$keys[$key_id]) ? static::$keys[$key_id] : $default_key;
                }
        }
    }

    /**
     * Load the default keys from the .env file.
     * 
     * @return void
     */
    public static function loadKeysFromEnv()
    {
        static::$keys = [];
        foreach ($_ENV as $k => $v) {
            preg_match_all('/JWT_KEY_(\w+)/', $k, $matches, PREG_SET_ORDER, 0);
            if (count($matches) < 1) {
                continue;
            }
            $match = $matches[0];
            $key = count($match) > 1 ? $match[1] : 'JWT';
            static::$keys[$key] = $v;
        }
    }

    /**
     * Approves the token by verifying its signature and claims.
     * 
     * @param  mixed $key The signing key encryption, if asymmetric
     * @return object The JWT's payload as a PHP object
     * @uses jsonDecode
     * @uses urlsafeB64Decode
     */
    public function approve($key = null)
    {
        $algo = $this->algorithm();
        $sign = $this->signature();

        if (empty(static::$supported_algs[$algo])) {
            throw new UnexpectedValueException('Algorithm not supported');
        }
        if (empty($this->signingInput)) {
            throw new InvalidArgumentException('Invalid signing input');
        }

        if (!isset($key)) {
            $kid = $this->kid();
            $key = $this->signingKey($kid, $algo, false);
        }

        if (empty($key)) {
            throw new InvalidArgumentException('Key may not be empty');
        }

        // Check the signature
        if (!$this->verify($this->signingInput, $sign, $key)) {
            throw new JWTException(JWTException::SIGNATURE_INVALID);
        }

        $timestamp = time();
        // Check if the nbf if it is defined. This is the time that the
        // token can actually be used. If it's not yet that time, abort.
        if (isset($this->payload->nbf) && $this->payload->nbf > ($timestamp + static::$leeway)) {
            throw new JWTException(JWTException::BEFORE_VALIDATION);
        }

        // Check that this token has been created before 'now'. This prevents
        // using tokens that have been created for later use (and haven't
        // correctly used the nbf claim).
        if (isset($this->payload->iat) && $this->payload->iat > ($timestamp + static::$leeway)) {
            throw new JWTException(JWTException::BEFORE_VALIDATION);
        }

        // Check if this token has expired.
        if (isset($this->payload->exp) && ($timestamp - static::$leeway) >= $this->payload->exp) {
            throw new JWTException(JWTException::EXPIRED_TOKEN);
        }

        return $this->payload;
    }

    /**
     * Converts and signs a PHP object or array into a JWT string.
     *
     * @param object|array  $payload    PHP object or array
     * @param OpenSSLAsymmetricKey|string|null  $key  The secret key
     * @param array         $head       An array with header elements to attach
     * @return string A signed JWT
     * @uses jsonEncode
     * @uses urlsafeB64Encode
     * @throws Exception Invalid Algorithm or OpenSSL failure
     */
    public function encode($payload, $key = null, $algo = 'HS256', $head = null)
    {
        $header_array = ['typ' => 'JWT', 'alg' => $algo];
        if (isset($head) && is_array($head)) {
            $header_array = array_merge($header_array, $head);
        }
        if (!isset($key)) {
            $kid = isset($header_array['kid']) ? $header_array['kid'] : null;
            $key = $this->signingKey($kid, $algo, true);
        }

        $segments = [];
        $segments[] = $this->urlsafeB64Encode($this->jsonEncode($header_array));

        $payload_array = array_merge([
            'jti'  => bin2hex(random_bytes(32)),
            'iss'  => $_SERVER['SERVER_NAME'] ?? 'localhost',
            'aud'  => $_SERVER['SERVER_NAME'] ?? 'localhost',
        ], $payload);
        $segments[] = $this->urlsafeB64Encode($this->jsonEncode($payload_array));

        $this->signingInput = implode('.', $segments);
        $this->signature = $this->sign($this->signingInput, $key, $algo);
        $segments[] = $this->urlsafeB64Encode($this->signature);

        $this->header = (object) $header_array;
        $this->payload = (object) $payload_array;

        return $this->token = implode('.', $segments);
    }

    /**
     * Sign a string with a given key and algorithm.
     * 
     * @param string  $msg    The message to sign
     * @param OpenSSLAsymmetricKey|OpenSSLCertificate|array|string  $key  The secret key
     * @param string  $algo   The hashing algorithm
     * @return string An encrypted message
     * @throws Exception Unsupported algorithm specified or unable to sign data
     */
    protected function sign($msg, $key, $algo)
    {
        if (empty(static::$supported_algs[$algo])) {
            throw new Exception('Algorithm not supported');
        }
        list($function, $algo) = static::$supported_algs[$algo];
        switch ($function) {
            case 'hash_hmac':
                return hash_hmac($algo, $msg, $key, true);
            case 'openssl':
                $signature = '';
                $success = openssl_sign($msg, $signature, $key, $algo);
                if (!$success) {
                    throw new Exception('OpenSSL unable to sign data');
                }
                return $signature;
        }
    }

    /**
     * Verify a signature with the message, key and method. Not all methods
     * are symmetric, so we must have a separate verify and sign method.
     *
     * @param string            $msg        The original message (header and body)
     * @param string            $signature  The original signature
     * @param OpenSSLAsymmetricKey|OpenSSLCertificate|array|string  $key For HS*, a string key works. for RS*, must be a resource of an openssl public key
     * @return bool
     * @throws Exception Invalid Algorithm or OpenSSL failure
     */
    protected function verify($msg, $signature, $key)
    {
        $algo = $this->algorithm();
        if (empty(static::$supported_algs[$algo])) {
            throw new Exception('Algorithm not supported');
        }
        if (empty($signature)) {
            throw new Exception('Signature is invalid');
        }

        list($function, $algo) = static::$supported_algs[$algo];
        switch ($function) {
            case 'openssl': {
                    $success = openssl_verify($msg, $signature, $key, $algo);
                    switch ($success) {
                        case 1:
                            return true;
                        case 0:
                            return false;
                        default:
                            throw new Exception('OpenSSL error: ' . openssl_error_string());
                    }
                }
            case 'hash_hmac':
            default: {
                    $hash = hash_hmac($algo, $msg, $key, true);
                    return hash_equals($signature, $hash);
                }
        }
    }

    /**
     * Decode a JSON string into a PHP object.
     *
     * @param string $input JSON string
     * @return object Object representation of JSON string
     * @throws Exception Provided string was invalid JSON
     */
    protected function jsonDecode($input)
    {
        $obj = json_decode($input, false, 512, JSON_BIGINT_AS_STRING);

        if (function_exists('json_last_error') && $errno = json_last_error()) {
            $this->handleJsonError($errno);
        } elseif ($obj === null && $input !== 'null') {
            throw new Exception('Null result with non-null input');
        }
        return $obj;
    }

    /**
     * Encode a PHP object into a JSON string.
     *
     * @param object|array $input A PHP object or array
     * @return string JSON representation of the PHP object or array
     * @throws Exception Provided object could not be encoded to valid JSON
     */
    protected function jsonEncode($input)
    {
        $json = json_encode($input);
        if (function_exists('json_last_error') && $errno = json_last_error()) {
            $this->handleJsonError($errno);
        } elseif ($json === 'null' && $input !== null) {
            throw new Exception('Null result with non-null input');
        }
        return $json;
    }

    /**
     * Decode a string with URL-safe Base64.
     *
     * @param string $input A Base64 encoded string
     * @return string A decoded string
     */
    protected function urlsafeB64Decode($input)
    {
        $remainder = strlen($input) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $input .= str_repeat('=', $padlen);
        }
        return base64_decode(strtr($input, '-_', '+/'));
    }

    /**
     * Encode a string with URL-safe Base64.
     *
     * @param string $input The string you want encoded
     * @return string The base64 encode of what you passed in
     */
    protected function urlsafeB64Encode($input)
    {
        return str_replace('=', '', strtr(base64_encode($input), '+/', '-_'));
    }

    /**
     * Helper method to create a JSON error.
     *
     * @param int $errno An error number from json_last_error()
     * @return void
     */
    protected function handleJsonError($errno)
    {
        $messages = [
            JSON_ERROR_DEPTH => 'Maximum stack depth exceeded',
            JSON_ERROR_STATE_MISMATCH => 'Invalid or malformed JSON',
            JSON_ERROR_CTRL_CHAR => 'Unexpected control character found',
            JSON_ERROR_SYNTAX => 'Syntax error, malformed JSON',
            JSON_ERROR_UTF8 => 'Malformed UTF-8 characters' //PHP >= 5.3.3
        ];
        $message = isset($messages[$errno]) ? $messages[$errno] : 'Unknown JSON error: ' . $errno;
        throw new Exception($message);
    }
}
