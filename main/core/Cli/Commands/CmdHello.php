<?php

namespace Core\Cli\Commands;

/**
 * CmdHello
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class CmdHello extends Cmd
{
    public function params()
    {
        return [
            'default' => 'Say hello :) !',
        ];
    }

    public function invoke($param, $args, $options)
    {
        $name = ucfirst(isset($args[0]) ? $args[0] : 'world');
        $this->printer
            ->newline()
            ->out("Hello, {green;bold}$name{nc}!")
            ->newline();
    }
}
