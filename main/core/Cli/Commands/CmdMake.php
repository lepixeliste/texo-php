<?php

namespace Core\Cli\Commands;

/**
 * CmdMake
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class CmdMake extends Cmd
{
    public function params()
    {
        return [
            'default' => 'Create a new controller, model and resource class file',
            'controller' => 'Create a new controller class file',
            'model' => 'Create a new model class file',
            'resource' => 'Create a new resource class file',
            'cast' => 'Create a new cast attribute class file',
            'middleware' => 'Create a new middleware class file',
        ];
    }

    public function invoke($param, $args, $options)
    {
        $this->printer->newline();

        if (empty($args)) {
            $this->printer
                ->out("{bgred;bold} ERROR {nc} {italic}make(:parameter?) [name]{nc} -> missing {yellow}[name]{nc} argument.")
                ->newline();
            exit;
        }

        $name = ucfirst($args[0]);

        $filetypes = [];
        switch ($param) {
            case 'model':
                $filetypes = ['Model'];
                break;
            case 'controller':
                $filetypes = ['Controller'];
                break;
            case 'resource':
                $filetypes = ['Resource'];
                break;
            case 'cast':
                $filetypes = ['Attribute'];
                break;
            case 'middleware':
                $filetypes = ['Middleware'];
                break;
            default:
                $filetypes = empty($param) ? ['Model', 'Controller', 'Resource'] : [];
                break;
        }

        if (empty($filetypes)) {
            $this->printer
                ->out("{bgred;bold} ERROR {nc} {italic}make(:parameter?) [name]{nc} -> invalid {yellow}(:parameter){nc} argument.")
                ->newline();
            exit;
        }

        foreach ($filetypes as $filetype) {
            $filename = $filetype !== 'Model' ? "{$name}{$filetype}" : $name;
            $to = $filetype !== 'Attribute' ? "{$filetype}s" : 'Casts';
            $dest = path_main('app', $to);
            if (!file_exists($dest)) {
                @mkdir($dest, 0777, true);
            }
            $dest_file = "app/$to/{$filename}.php";
            $cmd = strtolower($filetype);
            if (copy(path_main('core/Cli/Make', "Base{$filetype}.tmp"), path_main($dest_file))) {
                if ($contents = file_get_contents(path_main($dest_file))) {
                    $contents = $this->regReplace($contents, $filename);
                    if ($filetype === 'Model') {
                        $table_name = strtolower($name);
                        $contents = preg_replace('/%_TABLE_%/', "{$table_name}s", $contents);
                    }
                    file_put_contents(path_main($dest_file), $contents);
                }
                $this->printer->out("{green}make:{$cmd}{nc} > `{italic}main/$dest_file{nc}` created.");
            } else {
                $this->printer->out("{red}make:{$cmd}{nc} > unable to create `{italic}main/$dest_file{nc}`.");
            }
        }
        $this->printer->newline();
    }
}
