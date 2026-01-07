<?php

namespace Core\Cli\Commands;

use Core\Container;
use Core\App;
use Core\Env;
use Core\Pdo\Db;
use Exception;

/**
 * CmdSetup
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class CmdSetup extends Cmd
{
    public function params()
    {
        return [
            'default' => 'Setup App',
        ];
    }

    public function invoke($param, $args, $options)
    {
        $this->printer
            ->newline()
            ->out('{cyan;bold}------------')
            ->out('= Welcome! =')
            ->out('------------{nc}')
            ->out('Please answer the following questions {italic}(leave empty for default value){nc}:');

        Env::boot();

        $this->printer
            ->newline()
            ->out('{bold}APP{nc}')
            ->out('---');
        $app_name = $this->printer->ask('{bold}Name{nc} > ');

        $this->printer
            ->newline()
            ->out('{bold}DATABASE{nc}')
            ->out('--------');
        $sql_name = $this->printer->ask('{bold}Name{nc} > ');
        $sql_host = '';
        $sql_user = '';
        $sql_pass = '';
        if (!empty($sql_name)) {
            $sql_host = $this->printer->ask('{bold}Host{nc} > ');
            $sql_host = empty($sql_host) ? '127.0.0.1' : $sql_host;
            $sql_user = $this->printer->ask('{bold}Username{nc} > ');
            $sql_user = empty($sql_user) ? 'root' : $sql_user;
            $sql_pass = $this->printer->ask('{bold}Password{nc} > ', true);
        }

        Env::updateFile([
            'APP_NAME' => $app_name,
            'SQL_DB'   => $sql_name,
            'SQL_HOST' => $sql_host,
            'SQL_USER' => $sql_user,
            'SQL_PASS' => $sql_pass,
        ]);

        if (!empty($sql_name)) {
            $db = new Db($sql_name);
            try {
                $db->execute("CREATE DATABASE IF NOT EXISTS {$sql_name}; USE {$sql_name};");
                $db->buildSchema(true);
                $this->printer
                    ->out("{green}DATABASE{nc} > Create `{italic}$sql_name{nc}` if not exists.");
            } catch (Exception $e) {
                $error = $e->getMessage();
                $this->printer
                    ->out("{red}DATABASE{nc} > Could not create `{italic}$sql_name{nc}`.")
                    ->out("{red}DATABASE{nc} > $error");
            }
        }

        $this->printer
            ->newline()
            ->out('{green;bold}App successfully setup!{nc}')
            ->newline();
    }
}
