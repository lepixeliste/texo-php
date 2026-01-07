<?php

namespace Core\Cli;

use Core\Cli\Commands\Cmd;
use Core\Cli\Commands\CmdConfig;
use Core\Cli\Commands\CmdCopy;
use Core\Cli\Commands\CmdDb;
use Core\Cli\Commands\CmdHello;
use Core\Cli\Commands\CmdKey;
use Core\Cli\Commands\CmdMail;
use Core\Cli\Commands\CmdMake;
use Core\Cli\Commands\CmdPhar;
use Core\Cli\Commands\CmdRoute;
use Core\Cli\Commands\CmdSetup;
use Core\Cli\Commands\CmdTar;
use Core\Cli\Commands\CmdTask;

/**
 * CLI wrapper.
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class Interpreter
{
    /** @var \Core\Cli\Printer */
    protected $printer;

    /** @var array */
    protected $registry = [];

    /**
     * @param \Core\Cli\Printer $printer
     * @return void
     */
    public function __construct(Printer $printer)
    {
        $this->printer = $printer;
    }

    /**
     * Gets the printer instance.
     * @return \Core\Cli\Printer
     */
    public function printer()
    {
        return $this->printer;
    }

    /**
     * Registers a Command instance or Closure by name.
     * 
     * @param  string $command The command name
     * @param  \Closure|\Core\Cli\Commands\Cmd Any command or callable function
     * @return void
     */
    public function registerCommand($command, $callable_or_cmd)
    {
        $this->registry[$command] = $callable_or_cmd;
    }

    /**
     * Gets the Command instance or Closure by name.
     * 
     * @param  string $command The command name
     * @return \Closure|\Core\Cli\Commands\Cmd|null Any command or callable function
     */
    protected function getCommand($command)
    {
        return isset($this->registry[$command]) ? $this->registry[$command] : null;
    }

    /**
     * Outputs the command line.
     * 
     * @param  string[] $args
     * @return void
     */
    public function runCommand($args = [])
    {
        $def_cmd = 'list';
        $c = count($args);
        $arg = $c > 1 ? $args[1] : $def_cmd;
        $commands = preg_split('/\:/', $arg);

        $command_name = count($commands) > 0 ? $commands[0] : $def_cmd;
        if (empty($command_name)) {
            $this->printer
                ->newline()
                ->out("{bgred;bold} ERROR {nc} command is empty.")
                ->newline();
            exit;
        }

        $cmd = $this->getCommand($command_name);
        if (null === $cmd) {
            $this->printer
                ->newline()
                ->out("{bgred;bold} ERROR {nc} command `{italic;yellow}$command_name{nc}` not found.")
                ->newline();
            exit;
        }

        $parameter = count($commands) > 1 ? $commands[1] : '';
        $p = is_string($parameter) ? strtolower($parameter) : '';

        $arguments = $c > 2 ? array_slice($args, 2) : [];
        $a = array_values(
            array_filter($arguments, function ($arg) {
                return !(bool)preg_match('/-{1,2}[a-zA-Z0-9_=]+/', $arg);
            })
        );
        $options = array_values(
            array_filter($arguments, function ($arg) {
                return (bool)preg_match('/^-{1,2}[a-zA-Z0-9_=]+$/', $arg);
            })
        );
        $o = [];
        foreach ($options as $option) {
            $split = explode('=', $option);
            $key = isset($split[0]) ? $split[0] : '';
            if (empty($key)) {
                continue;
            }
            $o[$key] = isset($split[1]) ? $split[1] : '';
        }

        if (is_callable($cmd)) {
            call_user_func($cmd, $p, $a, $o);
        } else if ($cmd instanceof Cmd) {
            $cmd->invoke($p, $a, $o);
        }
    }

    /**
     * Registers the default command instances.
     * 
     * @return void
     */
    public function boot()
    {
        $commands = [
            CmdSetup::class,
            CmdTar::class, CmdPhar::class,
            CmdMake::class, CmdCopy::class,
            CmdRoute::class, CmdConfig::class,
            CmdDb::class, CmdKey::class,
            CmdMail::class, CmdTask::class,
            CmdHello::class
        ];

        $list = [];
        foreach ($commands as $command) {
            $cmd = new $command($this->printer());
            $cmd_name = trim(strtolower(preg_replace('/core\\\\cli\\\\commands\\\\cmd/i', '', $command)));
            if (!($cmd instanceof Cmd)) {
                continue;
            }
            $params = $cmd->params();
            foreach ($params as $param => $desc) {
                $cmd_name_with_param = $param !== 'default' ? "$cmd_name:$param" : $cmd_name;
                $tabs = strlen($cmd_name_with_param) < 12 ? "\t\t" : "\t";
                $list[] = "  * {green}{$cmd_name_with_param}{nc}{$tabs}{$desc}";
            }

            $this->registerCommand($cmd_name, $cmd);
        }

        $list_fn = function () use ($list) {
            $intro = '= PHP ' . phpversion() . ' CLI =';
            $line = implode('', array_fill(0, strlen($intro), '-'));
            $this->printer
                ->newline()
                ->out("{cyan;bold}$line")
                ->out("$intro")
                ->out("$line{nc}")
                ->newline()
                ->out("{yellow}Usage:{nc}\n  command(:parameter) [arguments]")
                ->newline()
                ->out("{yellow}Available commands:{nc}");
            foreach ($list as $cmd) {
                $this->printer->out($cmd);
            }
            $this->printer->newline();
        };
        $this->registerCommand('help', $list_fn);
        $this->registerCommand('list', $list_fn);

        $this->registerCommand('clock', function () {
            $clock = round(clock(), 6);
            $this->printer
                ->newline()
                ->out("{48;5;45;1} BOOT {nc} Executed in {$clock}s")
                ->newline();
        });
    }
}
