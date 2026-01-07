<?php

namespace Core\Auth;

/**
 * OpenSSL implementation for asymmetric encryption and decryption.
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class SSL
{
    /** @var string */
    protected $fileKey = '';

    /** 
     * @return void
     */
    public function __construct($file_key = '')
    {
        $this->fileKey = empty($file_key) ? 'app' : strtolower($file_key);
    }

    /**
     * Generates a new RSA private/public key.
     * 
     * @param  string  $passphrase An optional pass phrase to protect the generated key
     * @return bool
     */
    public static function generate($passphrase = null)
    {
        $private_key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        if (!$private_key) {
            return false;
        }

        $private_key_pem = '';
        $export = openssl_pkey_export($private_key, $private_key_pem, $passphrase);
        if (!$export) {
            return false;
        }

        $public_key_details = openssl_pkey_get_details($private_key);
        if (!$public_key_details || !isset($public_key_details['key'])) {
            return false;
        }

        $public_key_pem = $public_key_details['key'];

        return [
            'private' => $private_key_pem,
            'public' => $public_key_pem
        ];
    }

    /**
     * Creates new RSA private/public key files.
     * 
     * @param  string  $file_key The file key name
     * @param  string  $passphrase An optional pass phrase to protect the generated key
     * @return bool
     */
    public static function create($file_key, $passphrase = null)
    {
        $file_key = empty($file_key) ? 'app' : strtolower($file_key);
        $pass = preg_replace('/[^\w]+/', '', empty($passphrase) ? getenv('SSL_PASSPHRASE') : $passphrase);

        $key = static::generate($pass);
        if (!$key) {
            return false;
        }

        $ssl_path = path_root('.ssl');
        if (!file_exists($ssl_path)) {
            mkdir($ssl_path, 0700, true);
        }

        $files = ["{$file_key}_public_cert.pem" => $key['public'], "{$file_key}_private_cert.pem" => $key['private']];
        foreach ($files as $filename => $content) {
            $filepath = $ssl_path . DIRECTORY_SEPARATOR . $filename;
            file_put_contents($filepath, $content);
            chmod($filepath, 0600);
        }
        return true;
    }

    /**
     * Gets the RSA private key.
     * 
     * @param  string  $passphrase An optional pass phrase to protect the generated key
     * @return \OpenSSLAsymmetricKey|false
     */
    public function getPrivate($passphrase = null)
    {
        $pass = empty($passphrase) ? getenv('SSL_PASSPHRASE') : $passphrase;
        $key_path = 'file://' . path_root('.ssl', "{$this->fileKey}_private_cert.pem");
        return openssl_pkey_get_private($key_path, $pass);
    }

    /**
     * Gets the RSA public key.
     * 
     * @return \OpenSSLAsymmetricKey|false
     */
    public function getPublic()
    {
        $key_path = 'file://' . path_root('.ssl', "{$this->fileKey}_public_cert.pem");
        return openssl_pkey_get_public($key_path);
    }

    /**
     * Exports the RSA private/public key.
     * 
     * @param  string  $passphrase An optional pass phrase to protect the generated key
     * @return array|false
     */
    public function export($passphrase = null)
    {
        $pass = empty($passphrase) ? getenv('SSL_PASSPHRASE') : $passphrase;
        $private_key = $this->getPrivate($pass);
        if (!$private_key) {
            return false;
        }

        $private_key_pem = '';
        $export = openssl_pkey_export($private_key, $private_key_pem, $pass);
        if (!$export) {
            return false;
        }

        $public_key_details = openssl_pkey_get_details($private_key);
        if (!$public_key_details || !isset($public_key_details['key'])) {
            return false;
        }

        $public_key_pem = $public_key_details['key'];

        return [
            'private' => $private_key_pem,
            'public' => $public_key_pem
        ];
    }
}
