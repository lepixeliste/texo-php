<?php

namespace Core\Auth;

use Core\Pdo\Db;
use Core\Psr\Http\Message\RequestInterface;
use Exception;
use InvalidArgumentException;
use stdClass;

/**
 * The core class for authentication process. 
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class Auth
{
    /**
     * The Basic authorization string.
     *
     * @var string|null
     */
    protected $basic;

    /**
     * The JWT bearer token.
     *
     * @var \Core\Auth\JWT|null
     */
    protected $jwt;

    /**
     * The JWT decoded payload.
     *
     * @var object|null
     */
    protected $payload;

    /**
     * The Auth error message, if any.
     *
     * @var string|null
     */
    protected $error = null;

    /**
     * @param \Core\Psr\Http\Message\RequestInterface $request
     * @return void
     */
    public function __construct(RequestInterface $request)
    {
        if (!($request instanceof RequestInterface)) {
            throw new InvalidArgumentException('Invalid request argument');
        }

        if ($request->hasHeader('authorization')) {
            $auth_header = $request->getHeaderLine('authorization');
            if ((bool)preg_match('/(bearer)/i', $auth_header)) {
                $this->assertToken(trim(preg_replace('/(bearer)/i', '', $auth_header)));
            } elseif ((bool)preg_match('/(basic)/i', $auth_header)) {
                $this->basic = base64_decode(trim(preg_replace('/(basic)/i', '', $auth_header)));
            }
        }
    }

    /**
     * Creates a password hash.
     *
     * @param  string Any plain-text password
     * @return string
     */
    public static function hash($password)
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * The user model use for login.
     * 
     * @param \Core\Pdo\Db  $db     The Db instance
     * @param string        $model  The Model class name
     * @return \Core\Auth\Authenticable|null
     */
    public function user(Db $db, $model)
    {
        $user = new $model($db);
        return $user instanceof Authenticable ? $user : null;
    }

    /**
     * Gets the encoded JSON Web Token from an approved user.
     *
     * @param  string|int   $uid   The user id
     * @param  int          $dur   Expiration time
     * @param  string|null  $kid   Optional token key id
     * @return string
     */
    public function approve($uid, $dur, $kid = null)
    {
        $this->jwt = new JWT();
        $iat = time();
        $nbf = $iat + JWT::$leeway;
        $exp = $iat + $dur; // 1 hour = 3600 seconds
        $payload = [
            'iat'  => $iat,
            'nbf'  => $nbf,
            'exp'  => $exp,
            'user' => $uid
        ];
        return $this->jwt->encode($payload, null, getenv('JWT_ALGO'), is_string($kid) ? ['kid' => $kid] : null);
    }

    /**
     * Gets the string error, if any.
     *
     * @return string|null
     */
    public function error()
    {
        return is_string($this->error) && strlen($this->error) > 0 ? $this->error : null;
    }

    /**
     * Checks if the current user is authenticated.
     *
     * @return bool
     */
    public function isAuth()
    {
        return null !== $this->id() && null === $this->error();
    }

    /**
     * Returns the token payload, if decoded.
     *
     * @return object
     */
    public function payload()
    {
        return null !== $this->jwt ? $this->jwt->payload() : new stdClass();
    }

    /**
     * Returns the token user ID, if decoded.
     *
     * @return string|int|null
     */
    public function id()
    {
        $payload = $this->payload();
        return isset($payload->user) ? $payload->user : null;
    }

    /**
     * Returns the token key ID, if decoded.
     *
     * @return string|null
     */
    public function keyId()
    {
        return null !== $this->jwt ? $this->jwt->kid() : null;
    }

    /**
     * Returns the token jti for CSRF, if decoded.
     *
     * @return string|null
     */
    public function csrf()
    {
        $payload = $this->payload();
        return isset($payload->jti) ? $payload->jti : null;
    }

    /**
     * Sets up the token payload, if decoded.
     *
     * @param string $token JWT string to decode
     * @return void
     */
    protected function assertToken($token)
    {
        if (!is_string($token) || empty($token)) {
            $this->error = new JWTException(JWTException::INVALID_TOKEN);
            return;
        }

        $this->error = null;
        try {
            $this->jwt = new JWT($token);
            $this->jwt->approve();
        } catch (Exception $e) {
            $this->error = $e->getMessage();
        }
    }
}
