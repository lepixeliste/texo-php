<?php

namespace Core\Cli\Commands;

/**
 * CmdCopy
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class CmdCopy extends Cmd
{
    public function params()
    {
        return [
            'default' => 'Copy and rename an existing app class file',
        ];
    }

    public function invoke($param, $args, $options)
    {
        $this->printer->newline();

        $c = count($args);
        if ($c < 2) {
            $var = $c < 1 ? 'old_name' : 'new_name';
            $this->printer
                ->out("{bgred;bold} ERROR {nc} {italic}copy [old_name] [new_name]{nc} -> missing {yellow}[$var]{nc} argument.")
                ->newline();
            exit;
        }

        $old_name = ucfirst($args[0]);
        $new_name = ucfirst($args[1]);

        $filetype = 'Model';
        $types = ['Controller', 'Resource', 'Attribute', 'Middleware'];
        foreach ($types as $type) {
            if ((bool)preg_match('/' . $type . '/', $old_name)) {
                $filetype = $type;
            }
        }

        $to = $filetype !== 'Attribute' ? "{$filetype}s" : 'Casts';
        $dest = path_main('app', $to);
        if (!file_exists($dest)) {
            @mkdir($dest, 0777, true);
        }
        $old_file = "app/$to/{$old_name}.php";
        if (!file_exists(path_main($old_file))) {
            $this->printer
                ->out("{bgred;bold} ERROR {nc} missing `$old_file` file.")
                ->newline();
            exit;
        }

        $new_file = "app/$to/{$new_name}.php";
        if (copy(path_main($old_file), path_main($new_file))) {
            if ($contents = file_get_contents(path_main($new_file))) {
                $contents = preg_replace('/' . $old_name . '/', $new_name, $contents);
                if ($filetype === 'Model') {
                    $old_table = strtolower($old_name);
                    $new_table = strtolower($new_name);
                    $contents = preg_replace('/' . $old_table . '/', $new_table, $contents);
                }
                file_put_contents(path_main($new_file), $contents);
            }
            $this->printer
                ->out("{green}copy{nc} > `{italic}main/$new_file{nc}` created.");
        } else {
            $this->printer
                ->out("{red}copy{nc} > unable to create `{italic}main/$new_file{nc}`.");
        }

        $this->printer->newline();
    }
}
