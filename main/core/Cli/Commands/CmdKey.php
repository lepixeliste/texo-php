<?php

namespace Core\Cli\Commands;

use Exception;
use Core\Auth\Auth;
use Core\Auth\JWT;
use Core\Auth\SSL;

/**
 * CmdKey
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class CmdKey extends Cmd
{
    public function params()
    {
        return [
            'hash' => 'Generate a password hash',
            'random' => 'Generate a randomized string',
            'ssl' => 'Generate a new OpenSSL private/public key',
        ];
    }

    public function invoke($param, $args, $options)
    {
        $this->printer->newline();

        switch ($param) {
            case 'hash': {
                    $str = !empty($args) && isset($args[0]) ? $args[0] : random_string(8);
                    $hash = Auth::hash($str);
                    $this->printer
                        ->out("{green}key:$param{nc} > $str => $hash");
                    break;
                }
            case 'random': {
                    $len = !empty($args) && isset($args[0]) ? $args[0] : 12;
                    $random = random_string($len);
                    $this->printer
                        ->out("{green}key:$param{nc} > $random");
                    break;
                }
            case 'ssl': {
                    $key_file = preg_replace('/[^\w]+/', '', !empty($args) && isset($args[0]) ? $args[0] : 'app');
                    $success = SSL::create($key_file);
                    if (!$success) {
                        $this->printer
                            ->out("{red}key:$param{nc} > Could not generate a new private/public key.");
                        break;
                    }
                    $ssl = new SSL($key_file);
                    $key = $ssl->export();
                    if (!$key) {
                        $this->printer
                            ->out("{red}key:$param{nc} > Could not export the new private/public key.");
                        break;
                    }

                    $this->printer
                        ->out("{green}key:$param{nc} > Generating new private/public key:")
                        ->newline()
                        ->out($key['private'])
                        ->out($key['public']);
                    break;
                }
            case 'jwt': {
                    $ssl = new SSL('jwt');
                    $private_key = $ssl->getPrivate();
                    if (!$private_key) {
                        $this->printer
                            ->out("{red}key:$param{nc} > Could not read private key `{italic}jwt{nc}`");
                        break;
                    }

                    try {
                        $jwt = new JWT();
                        $token = $jwt->encode(['user' => uniqid()], $private_key, 'RS256', ['kid' => 'USER']);
                        $this->printer
                            ->out("{green}key:$param{nc} > Token: {italic}$token{nc}");
                    } catch (Exception $e) {
                        $error = $e->getMessage();
                        $this->printer
                            ->out("{red}key:$param{nc} > $error");
                    }
                    break;
                }
            default: {
                    $this->printer
                        ->out("{bgred;bold} ERROR {nc} {italic}key(:parameter) [argument?]{nc} -> invalid {yellow}(:parameter){nc} argument.");
                    break;
                }
        }

        $this->printer->newline();
    }
}
