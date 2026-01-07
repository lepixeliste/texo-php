<?php

namespace Core\Cli\Commands;

/**
 * CmdRoute
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class CmdRoute extends Cmd
{
    public function params()
    {
        return [
            'add' => 'Create a new route list file'
        ];
    }

    public function invoke($param, $args, $options)
    {
        $this->printer->newline();

        switch ($param) {
            case 'add': {
                    $name = isset($args[0]) ? $args[0] : '';
                    $filename = ucfirst($name);
                    $dest = path_main('routes');
                    if (!file_exists($dest)) {
                        @mkdir($dest, 0777, true);
                    }
                    if (empty($name)) {
                        $this->printer
                            ->out("{bgred;bold} ERROR {nc} {italic}route:{$param} [name]{nc} -> missing {yellow}[name]{nc} argument.")
                            ->newline();
                        exit;
                    }

                    $dest_file = "routes/{$filename}.php";
                    if (copy(path_main("core/Cli/Make/BaseRoute.tmp"), path_main($dest_file))) {
                        if ($contents = file_get_contents(path_main($dest_file))) {
                            $contents = $this->regReplace($contents, $filename);
                            file_put_contents(path_main($dest_file), $contents);
                        }
                        $this->printer
                            ->out("{green}route:{$param}{nc} > `main/$dest_file` created.");
                    } else {
                        $this->printer
                            ->out("{red}route:{$param}{nc} > unable to create `main/$dest_file`.");
                    }
                    break;
                }
            default: {
                    $this->printer
                        ->out("{bgred;bold} ERROR {nc} {italic}route(:parameter) [argument?]{nc} -> invalid {yellow}(:parameter){nc} argument.");
                    break;
                }
        }

        $this->printer->newline();
    }
}
