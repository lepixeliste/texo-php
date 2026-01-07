<?php

namespace Core\Cli\Commands;

use Exception;
use Core\Pdo\Db;
use RuntimeException;

/**
 * CmdDb
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class CmdDb extends Cmd
{
    public function params()
    {
        return [
            'create' => 'Create a new database if not exists',
            'use' => 'Switch to database',
            'drop' => 'Drop database',
            'dump' => 'Backup database utility',
            'import' => 'Import SQL file into any new or existing database',
            'schema' => 'Build/rebuild the database schema file',
            'ddl' => 'Build/rebuild the database Data Definition Language file',
            'build' => 'Build the database from the latest DDL file',
            'collate' => 'Alter database to current charset and collate',
        ];
    }

    public function invoke($param, $args, $options)
    {
        $this->printer->newline();

        $dbname = !empty($args) ? $args[0] : null;
        if (!isset($dbname)) {
            $dbname = getenv('SQL_DB');
        }

        $dir = path_root('db');
        if (!file_exists($dir)) {
            @mkdir($dir, 0777, true);
        }

        switch ($param) {
            case 'create': {
                    $user = getenv('SQL_USER');
                    $host = getenv('SQL_HOST');

                    $r_code = 0;
                    $r_output = [];
                    $e = exec("mariadb -h $host -u $user -p -e \"CREATE DATABASE IF NOT EXISTS \\`$dbname\\`; USE \\`$dbname\\`;\"", $r_output, $r_code);
                    $clock = round(clock(), 3);
                    $this->printer
                        ->out($e !== false && $r_code === 0 ? "{green}db:$param{nc} > Create `{italic}$dbname{nc}` if not exists." : "{red}db:$param{nc} > Could not create `{italic}$dbname{nc}`.")
                        ->out("{blue}db:$param{nc} > Executed in {$clock}s");
                    break;
                }
            case 'drop': {
                    if (is_env_prod()) {
                        $this->printer
                            ->out("{red}db:$param{nc} > This operation cannot be done in production mode.");
                        break;
                    }
                    $user = getenv('SQL_USER');
                    $host = getenv('SQL_HOST');

                    $r_code = 0;
                    $r_output = [];
                    $e = exec("mariadb -h $host -u $user -p -e \"DROP DATABASE IF EXISTS \\`$dbname\\`;\"", $r_output, $r_code);
                    $this->printer
                        ->out($e !== false && $r_code === 0 ? "{green}db:$param{nc} > `{italic}$dbname{nc}` dropped." : "{red}db:$param{nc} > Could not drop `{italic}$dbname{nc}`.");
                    break;
                }
            case 'use': {
                    $user = getenv('SQL_USER');
                    $host = getenv('SQL_HOST');

                    $e = exec("mariadb -h $host -u $user -p -e \"USE \\`$dbname\\`\"");
                    $this->printer
                        ->out($e !== false ? "{green}db:$param{nc} > Switch to `{italic}$dbname{nc}`." : "{red}db:$param{nc} > Could not switch to `{italic}$dbname{nc}`.");
                    break;
                }
            case 'schema': {
                    $db = new Db($dbname);
                    $schema = $db->buildSchema(true);
                    $this->printer
                        ->out($schema !== false ? "{green}db:$param{nc} > `$schema`." : "{red}db:$param{nc} > Could not build schema for `$dbname`.");
                    break;
                }
            case 'ddl': {
                    $db = new Db($dbname);
                    $copy = isset($options['--copy']) ? $options['--copy'] : '';
                    $ddl = $db->buildDDL($copy);
                    $this->printer
                        ->out($ddl !== false ? "{green}db:$param{nc} > `$ddl`." : "{red}db:$param{nc} > Could not build schema for `$dbname`.");
                    break;
                }
            case 'build': {
                    $db = new Db($dbname);
                    try {
                        $db->build();
                        $this->printer
                            ->out("{green}db:$param{nc} > `$dbname` built successfully.");
                    } catch (Exception $e) {
                        $error_msg = $e->getMessage();
                        $this->printer
                            ->out("{red}db:$param{nc} > $error_msg");
                    }
                    break;
                }
            case 'collate': {
                    $db = new Db($dbname);

                    $charset = getenv('SQL_CHARSET');
                    $collate = getenv('SQL_COLLATE');

                    $alter_database = "ALTER DATABASE `$dbname` CHARACTER SET $charset COLLATE $collate;";
                    $db->execute($alter_database);
                    $this->printer->out("{green}mysql{nc} > $alter_database");

                    $tables = $db->execute("SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=? AND TABLE_TYPE='BASE TABLE';", [$dbname], null);
                    foreach ($tables as $table) {
                        $table_name = get_value('TABLE_NAME', $table);
                        $alter_table = "ALTER TABLE `$table_name` CONVERT TO CHARACTER SET $charset COLLATE $collate;";
                        try {
                            $db->execute($alter_table);
                            $this->printer->out("{green}mysql{nc} > $alter_table");
                        } catch (Exception $e) {
                            $message = $e->getMessage();
                            $this->printer->out("{red}mysql{nc} > $message");
                        }
                    }
                    break;
                }
            case 'dump': {
                    $no_data = isset($options['-d']);
                    $filename = date('Ymd\THis') . '_' . $dbname . ($no_data ? '_ddl' : '') . '.sql';
                    $dest = "$dir/$filename";
                    $user = getenv('SQL_USER');
                    $pass = getenv('SQL_PASS');
                    $host = getenv('SQL_HOST');

                    $cl_dump = $no_data ? 'mariadb-dump -d' : 'mariadb-dump';
                    $cl = "{$cl_dump} -h $host -u $user -p{$pass} $dbname > {$dest}";
                    $e = exec($cl);
                    $clock = round(clock(), 3);
                    $this->printer
                        ->out($e !== false ? "{green}db:$param{nc} > Dump to `{italic}db/$filename{nc}`." : "{red}db:$param{nc} > Could not dump `{italic}$dbname{nc}`.")
                        ->out("{blue}db:$param{nc} > Executed in {$clock}s");
                    break;
                }
            case 'import': {
                    $dbname = isset($args[1]) ? strtolower($args[1]) : null;
                    if (!isset($dbname)) {
                        $dbname = getenv('SQL_DB');
                    }
                    $filename = isset($args[0]) ? $args[0] : '';
                    if (empty($filename)) {
                        $paths = glob($dir . '/*.sql');
                        if ($paths === false) {
                            throw new RuntimeException("Unable to scan `$dir`", 0);
                        }
                        if (empty($paths)) {
                            $this->printer
                                ->out("{bgred;bold} ERROR {nc} {italic}db:$param [filename] [dbname?]{nc} -> invalid {yellow}[filename]{nc} argument.")
                                ->newline();
                            exit;
                        }

                        $filename = basename($paths[count($paths) - 1]);
                        $this->printer
                            ->out("{green}db:$param{nc} > Found `{italic}$filename{nc}` to import.");
                    }

                    $dest = "$dir/$filename";
                    if (!file_exists($dest)) {
                        $this->printer
                            ->out("{bgred;bold} ERROR {nc} Could not find `$filename`.")
                            ->newline();
                        exit;
                    }

                    $user = getenv('SQL_USER');
                    $host = getenv('SQL_HOST');

                    $e = exec("mariadb -h $host -u $user -p -e \"CREATE DATABASE IF NOT EXISTS \\`$dbname\\`; USE \\`$dbname\\`;\"");
                    $this->printer->out($e !== false ? "{green}db:$param{nc} > Create `{italic}$dbname{nc}` if not exists." : "{red}db:$param{nc} > Could not create `{italic}$dbname{nc}`.");
                    if ($e !== false) {
                        $e = exec("mariadb -h $host -u $user -p $dbname < $dest");
                    }
                    $clock = round(clock(), 3);
                    $this->printer
                        ->out($e !== false ? "{green}db:$param{nc} > Imported to `{italic}$dbname{nc}`." : "{red}db:$param{nc} > Could not import `{italic}$dbname{nc}`.")
                        ->out("{blue}db:$param{nc} > Executed in {$clock}s");
                    break;
                }
            default: {
                    $this->printer
                        ->out("{bgred;bold} ERROR {nc} {italic}db(:parameter) [argument?]{nc} -> invalid {yellow}(:parameter){nc} argument.");
                    break;
                }
        }

        $this->printer->newline();
    }
};
