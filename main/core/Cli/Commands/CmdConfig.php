<?php

namespace Core\Cli\Commands;

use Core\Env;
use Exception;

/**
 * CmdConfig
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class CmdConfig extends Cmd
{
    public function params()
    {
        return [
            'env' => 'Add a new .env file',
        ];
    }

    public function invoke($param, $args, $options)
    {
        $this->printer->newline();

        switch ($param) {
            case 'env': {
                    try {
                        $filename = !empty($args) ? strtolower($args[0]) : '';
                        $filepath = Env::createFile($filename);
                        $this->printer
                            ->out("{green}config:{$param}{nc} > `{italic}$filepath{nc}` created.");
                    } catch (Exception $e) {
                        $error = $e->getMessage();
                        $this->printer
                            ->out("{bgred;bold} ERROR {nc} $error.")
                            ->newline();
                    }
                    break;
                }
            default: {
                    $this->printer
                        ->out("{bgred;bold} ERROR {nc} {italic}config(:parameter) [name?]{nc} -> invalid {yellow}(:parameter){nc} argument.");
                    break;
                }
        }

        $this->printer->newline();
    }
}
