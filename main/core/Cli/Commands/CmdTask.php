<?php

namespace Core\Cli\Commands;

use Core\Container;
use Core\App;
use Core\Pdo\Db;

/**
 * CmdTask
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class CmdTask extends Cmd
{
    public function params()
    {
        return [
            'create' => 'Create a new task operation file',
            'run' => 'Launch task operations (-l for latest task only)'
        ];
    }

    public function invoke($param, $args, $options)
    {
        $this->printer->newline();

        $name = isset($args[0]) ? ucfirst($args[0]) : '';

        switch ($param) {
            case 'create': {
                    $filename = date('Ymd\THis') . '_' . snake_case($name);
                    $dest = path_main('tasks');
                    if (!file_exists($dest)) {
                        @mkdir($dest, 0777, true);
                    }
                    $dest_file = "tasks/{$filename}.php";
                    if (copy(path_main("core/Cli/Make/BaseTask.tmp"), path_main($dest_file))) {
                        if ($contents = file_get_contents(path_main($dest_file))) {
                            $contents = $this->regReplace($contents, $filename);
                            file_put_contents(path_main($dest_file), $contents);
                        }
                        $this->printer
                            ->out("{green}task:$param{nc} > `{italic}main/$dest_file{nc}` created.");
                    } else {
                        $this->printer
                            ->out("{red}task:$param{nc} > unable to create `{italic}main/$dest_file{nc}`.");
                    }
                    break;
                }
            case 'run': {
                    $container = new Container();
                    $app = $container->get('Core\App');
                    if (!($app instanceof App)) {
                        $this->printer
                            ->out("{bgred;bold} ERROR {nc} > App could not be instantiated.")
                            ->newline();
                        exit;
                    }

                    $app->task(function ($message, $code) {
                        $this->printer->out($code === 0 ? "{bgred;bold} ERROR {nc} > {$message}" : "{green}$message{nc}");
                    }, isset($options['-l']));

                    $db = new Db(getenv('SQL_DB'));
                    $db->buildSchema(true);

                    $clock = round(clock(), 3);
                    $mem_usage = convert_bytes(memory_get_usage());
                    $peak_usage = convert_bytes(memory_get_peak_usage());

                    $time_elapsed = sprintf('%02d:%02d:%02d', intval($clock / 3600), intval(floor($clock / 60) % 60), intval($clock % 60));

                    $this->printer
                        ->out("{48;5;45;1} TASK {nc} > Executed in {$time_elapsed}s");
                    $this->printer
                        ->out("{48;5;45;1} TASK {nc} > Memory allocated $mem_usage | Peak Memory allocated $peak_usage");
                    break;
                }
            default: {
                    $this->printer
                        ->out("{bgred;bold} ERROR {nc} {italic}task(:parameter) [argument?]{nc} -> invalid {yellow}(:parameter){nc} argument.");
                    break;
                }
        }

        $this->printer->newline();
    }
}
