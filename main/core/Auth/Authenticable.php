<?php

namespace Core\Auth;

use Core\Pdo\Model;
use Core\Pdo\SqlQuery;

/**
 * Abstract Model class to help with the authentication process.
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

abstract class Authenticable extends Model
{
    /**
     * The name of the "username" column to check in database.
     *
     * @var string
     */
    const USERNAME_COLUMN = 'email';

    /**
     * The name of the "password" column to check in database.
     *
     * @var string
     */
    const PASSWORD_COLUMN = 'password';

    /**
     * The name of the password "token" column to check in database.
     *
     * @var string
     */
    const TOKEN_COLUMN = 'token';

    /**
     * Proceeds with the authentication via the Auth class.
     * 
     * @param \Core\Auth\Auth $auth The Auth class
     * @param string[] $credentials The credentials
     * @param int The token duration in seconds
     * @param string|null An optional token key id
     * @return string The authentication token
     * @throws \Core\Auth\AuthException
     */
    public function attempt(Auth $auth, $credentials, $duration, $key_id = null)
    {
        $user_column = static::USERNAME_COLUMN;
        $pass_column = static::PASSWORD_COLUMN;
        $primary = $this->primaryKey();

        $user = isset($credentials[0]) ? $credentials[0] : '';
        $pass = isset($credentials[1]) ? $credentials[1] : '';

        if (empty($user) || empty($pass)) {
            throw new AuthException(AuthException::INVALID_USER);
        }

        $query = SqlQuery::table($this->table())
            ->select($primary, $user_column, $pass_column)
            ->where($user_column, '=', $user);

        $result = $query->run($this->db)->first();
        if (null === $result) {
            throw new AuthException(AuthException::INVALID_USER);
        }

        $verify = password_verify($pass, $result->$pass_column);
        if (!$verify) {
            throw new AuthException(AuthException::INVALID_PASS);
        }

        return $auth->approve($result->$primary, $duration, $key_id);
    }

    /**
     * Invalidate the authentication token.
     * 
     * @return bool
     */
    public function invalidate()
    {
        if (null === static::TOKEN_COLUMN) {
            return false;
        }

        SqlQuery::table($this->table())
            ->update([static::TOKEN_COLUMN => null])
            ->where($this->primaryKey(), '=', $this->id())
            ->run($this->db);
        return true;
    }
}
