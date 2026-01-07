<?php

namespace Core\Cli\Commands;

use Core\Env;
use Core\Mail\Smtp;

/**
 * CmdMail
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class CmdMail extends Cmd
{
    public function params()
    {
        return [
            'setup' => 'Setup the SMTP configuration',
            'test' => 'Test the current SMTP configuration',
        ];
    }

    public function invoke($param, $args, $options)
    {
        $this->printer->newline();
        switch ($param) {
            case 'setup': {
                    $host = $this->printer
                        ->ask('{bold}SMTP Host{nc} > ');
                    $port = $this->printer
                        ->ask('{bold}SMTP Port{nc} {italic}(465 by default){nc} > ');
                    $email = $this->printer
                        ->ask('{bold}SMTP Email{nc} > ');
                    $user = $this->printer
                        ->ask('{bold}SMTP Username{nc} {italic}(if different from email){nc} > ');
                    $pass = $this->printer
                        ->ask('{bold}SMTP Password{nc} > ', true);

                    Env::updateFile([
                        'SMTP_HOST'  => $host,
                        'SMTP_PORT'  => empty($port) ? 465 : intval($port),
                        'SMTP_EMAIL' => $email,
                        'SMTP_USER'  => empty($user) ? $email : $user,
                        'SMTP_PASS'  => $pass
                    ]);

                    $this->printer->newline();
                    $smtp = new Smtp(getenv('SMTP_HOST'), getenv('SMTP_PORT'));
                    $smtp->ping($this->printer);

                    break;
                }
            case 'test': {
                    $smtp = new Smtp(getenv('SMTP_HOST'), getenv('SMTP_PORT'));
                    $smtp->ping($this->printer);
                    break;
                }
            default: {
                    $this->printer
                        ->out("{bgred;bold} ERROR {nc} {italic}mail(:parameter) [argument?]{nc} -> invalid {yellow}(:parameter){nc} argument.");
                    break;
                }
        }
        $this->printer->newline();
    }
}
