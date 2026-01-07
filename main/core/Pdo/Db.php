<?php

namespace Core\Pdo;

use Exception;
use PDO;
use Core\Logger;
use Core\Psr\Log\LogLevel;

/**
 * Convenient PDO wrapper for managing interactions between the script and a database server.
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class Db
{
    /**
     * The PDO instance.
     *
     * @var \PDO
     */
    protected $pdo;

    /**
     * The Db DSN.
     *
     * @var string
     */
    protected $dsn;

    /**
     * The Db DNS.
     *
     * @var string
     */
    protected $name;

    /**
     * The Db username.
     *
     * @var string
     */
    protected $username;

    /**
     * The Db password.
     *
     * @var string
     */
    protected $password;

    /**
     * The PDO last returned Id.
     *
     * @var string|null
     */
    protected $lastInsertId;

    /**
     * The default behaviour for buffered statements of the MySQL / MariaDb API.
     * 
     * @var bool
     */
    protected $buffered = true;

    /**
     * @param  string|null $dbname The database name to work with, or the default database name from .env file if not set
     * @return void
     */
    public function __construct($dbname = null)
    {
        $this->name = (isset($dbname) ? $dbname : getenv('SQL_DB'));
        $this->dsn      = 'mysql:host=' . getenv('SQL_HOST') . ';port=' . getenv('SQL_PORT') . ';dbname=' . $this->name . ';charset=' . getenv('SQL_CHARSET');
        $this->username = getenv('SQL_USER');
        $this->password = getenv('SQL_PASS');
    }

    /**
     * Gets the database name.
     * 
     * @return string
     */
    public function name()
    {
        return $this->name;
    }

    /**
     * If this attribute is set to FALSE before execute,
     * the MySQL / MariaDb driver will not use the default buffered versions of the MySQL / MariaDb API.
     * 
     * @param  bool $b
     * @return void
     */
    public function setBuffered($b)
    {
        $this->buffered = $b;
    }

    /**
     * Executes a prepared statement.
     * 
     * @param  string $query Any valid SQL statement
     * @param  array $args The query binding values
     * @param  string|null $fetch_class Specifies that the fetch method shall return a new instance of the requested class, if not null 
     * @return \Core\Collection
     */
    public function execute($query, $args = [], $fetch_class = DbRow::class)
    {
        if (!isset($this->pdo)) {
            $this->pdo = new PDO($this->dsn, $this->username, $this->password);
            if ($this->buffered === false) {
                $this->pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
            }
        }

        $this->lastInsertId = null;
        $fetched_results = [];
        if (!is_string($query) || empty($query)) {
            throw new DbQueryException(DbQueryException::EMPTY_QUERY);
        }

        try {
            $options = $this->buffered === false ? [PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false] : [];
            $stmt  = $this->pdo->prepare($query, $options);
            if (!$stmt) {
                throw new DbQueryException(DbQueryException::INVALID_QUERY);
            }
            $index = 1;

            foreach ($args as $arg) {
                $type = PDO::PARAM_STR;
                if (is_array($arg)) {
                    $arg = json_encode($arg);
                } elseif (is_object($arg)) {
                    $arg = strval($arg);
                } elseif (is_numeric($arg) && is_int($arg)) {
                    $type = PDO::PARAM_INT;
                } elseif (is_bool($arg)) {
                    $type = PDO::PARAM_BOOL;
                }

                $stmt->bindValue($index, $arg, $type);
                $index++;
            }

            if (!$stmt->execute()) {
                throw new DbQueryException(DbQueryException::INVALID_QUERY);
            }

            $fetched_results = $fetch_class === null ? $stmt->fetchAll() : $stmt->fetchAll(PDO::FETCH_CLASS, $fetch_class, [$this]);
            if ((bool)preg_match('/insert|update/i', $query)) {
                $last_id = $this->pdo->lastInsertId();
                $this->lastInsertId = !$last_id ? null : $last_id;
            }
            $stmt->closeCursor();
        } catch (Exception $e) {
            $error = new DbException($e->getMessage(), 0, $e);
            $combined = SqlQuery::combine($query, $args);
            Logger::print(LogLevel::ERROR, '[' . static::class . ']: error on query => ' . "'$combined'");
            throw $error;
        }

        if ($this->buffered === false) {
            $this->pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
            $this->buffered = true;
        }

        return collect($fetched_results);
    }

    /**
     * Gets the last returned Id, if any.
     * 
     * @return string|int|null
     */
    public function lastInsertId()
    {
        return $this->lastInsertId;
    }

    /**
     * Gets the current database configuration.
     * 
     * @return array
     */
    public function config()
    {
        $schema = $this->buildSchema();
        return [
            'driver' => 'sql',
            'schema' => $schema !== false ? include $schema : []
        ];
    }

    /**
     * Builds the current database schema file.
     * 
     * @param  boolean $coerced Forces rebuilding the schema file
     * @return string The filepath to the generated schema file
     */
    public function buildSchema($coerced = false)
    {
        $filename = 'schema-' . $this->name();
        $filepath = $this->getDbFile($filename);
        if (file_exists($filepath) && !$coerced) {
            return $filepath;
        }

        $res = $this->createDbFile($filename);
        if (!$res) {
            return false;
        }

        $structure = [];
        try {
            $tables = $this->execute('SHOW TABLES FROM `' . $this->name . '`')
                ->map(function ($row) {
                    $key = 'Tables_in_' . $this->name;
                    return $row->{$key};
                })->all();

            foreach ($tables as $table) {
                try {
                    $fields = $this->execute('SHOW COLUMNS FROM `' . $table . '`')->all();
                    $structure[] = [
                        'name' => $table,
                        'fields' => $fields
                    ];
                } catch (Exception $e) {
                    Logger::print(LogLevel::ERROR, $e->getMessage());
                    continue;
                }
            }
        } catch (Exception $e) {
            Logger::print(LogLevel::ERROR, $e->getMessage());
            return false;
        }

        $string = '<?php' . PHP_EOL . PHP_EOL;
        $string .= 'return [' . PHP_EOL . implode(PHP_EOL, array_map(function ($item) {
            $key = $item['name'];
            $fields = $item['fields'];
            $nl = PHP_EOL;
            $fields = implode(PHP_EOL, array_map(function ($row) use ($nl) {
                $name = $row->Field;
                $row_type = $row->Type;
                preg_match('/(\w+)\(?(\d+)?\)?/s', $row_type, $matches);
                $type = isset($matches[1]) ? "'{$matches[1]}'" : "'undefined'";
                $type_len = intval(isset($matches[2]) ? $matches[2] : null);
                $nullable = $row->Null === 'YES' ? 'true' : 'false';
                $key = is_string($row->Key) && !empty($row->Key) ? "'{$row->Key}'" : 'null';
                $items = [['key' => 'type', 'value' => $type]];
                if ($type_len > 0) {
                    $items[] = ['key' => 'length', 'value' => $type_len];
                }
                $items[] = ['key' => 'nullable', 'value' => $nullable];
                $items[] = ['key' => 'key', 'value' => $key];
                $items_str = implode($nl, array_map(function ($item) {
                    $item_key = $item['key'];
                    $item_value = $item['value'];
                    return "\t\t\t'$item_key' => $item_value,";
                }, $items));
                return "\t\t'{$name}' => [{$nl}{$items_str}{$nl}\t\t],";
            }, $fields));
            return "\t'{$key}' => [{$nl}{$fields}{$nl}\t],";
        }, $structure)) . PHP_EOL . '];' . PHP_EOL;
        $string .= PHP_EOL;

        $fwrite = 0;
        for ($written = 0; $written < strlen($string); $written += $fwrite) {
            $fwrite = fwrite($res, substr($string, $written));
            if ($fwrite === false || $fwrite < 1) {
                break;
            }
        }
        fclose($res);

        return $filepath;
    }

    /**
     * Builds the Data Definition Language file for MySql Database.
     * 
     * @param  string $to_filename The utility filename, or database name by default
     * @return string The filepath to the generated schema file
     */
    public function buildDDL($to_filename = '')
    {
        $filename = empty($to_filename) ? 'ddl-' . $this->name() : $to_filename;
        $res = $this->createDbFile($filename);
        if (!$res) {
            return false;
        }

        $definitions = [];
        try {
            $tables = $this->execute('SHOW TABLES FROM `' . $this->name . '`')
                ->map(function ($row) {
                    $key = 'Tables_in_' . $this->name;
                    return $row->{$key};
                })->all();

            foreach ($tables as $table) {
                try {
                    $row = $this->execute('SHOW CREATE TABLE `' . $table . '`')->first();
                    if (null === $row) continue;
                    $definitions[$table] = get_value('Create Table', $row, '');
                } catch (Exception $e) {
                    Logger::print(LogLevel::ERROR, $e->getMessage());
                    continue;
                }
            }
        } catch (Exception $e) {
            Logger::print(LogLevel::ERROR, $e->getMessage());
            return false;
        }

        $string = '<?php' . PHP_EOL . PHP_EOL;
        $string .= 'return [' . PHP_EOL;
        foreach ($definitions as $table => $def) {
            preg_match_all('/\((.+)\)/is', $def, $matches, PREG_SET_ORDER, 0);
            $match = isset($matches[0]) ? $matches[0] : [];
            $m = count($match) > 0 ? $match[count($match) - 1] : '';
            if (empty($m) || !is_string($m)) continue;
            $m = trim(preg_replace('/\s{2,}/is', ' ', $match[count($match) - 1]));
            $split = implode("\r\n", array_map(function ($row) {
                $trim_row = trim($row);
                return "\t\t\"{$trim_row}\",";
            }, explode(',', $m)));
            $string .= "\t'{$table}' => [\r\n{$split}\r\n\t]," . PHP_EOL;
        }
        $string .= '];' . PHP_EOL . PHP_EOL;

        $fwrite = 0;
        for ($written = 0; $written < strlen($string); $written += $fwrite) {
            $fwrite = fwrite($res, substr($string, $written));
            if ($fwrite === false || $fwrite < 1) {
                break;
            }
        }
        fclose($res);

        return $this->getDbFile($filename);
    }

    /**
     * Builds the database from the DDL file.
     * 
     * @return boolean True if successful
     */
    function build()
    {
        $filename = 'ddl-' . $this->name();
        $ddl_file = $this->getDbFile($filename);
        $ddl = $ddl_file !== false ? include $ddl_file : [];
        if (!is_array($ddl) || empty($ddl)) return false;
        // $charset = getenv('SQL_CHARSET');
        // $collate = getenv('SQL_COLLATE');
        $query = [];
        $query[] = 'SET FOREIGN_KEY_CHECKS=0';
        foreach ($ddl as $table => $defs) {
            $def = is_array($defs) ? implode(', ', $defs) : '';
            if (empty($def)) continue;
            $arg = "CREATE TABLE IF NOT EXISTS `{$table}` ({$def})";
            // $arg .= " DEFAULT CHARSET={$charset} COLLATE={$collate}";
            $query[] = $arg;
        }
        $query[] = 'SET FOREIGN_KEY_CHECKS=1';
        $this->execute(implode(";\r\n", $query));
        $this->buildSchema(true);
        return true;
    }

    /**
     * Returns the Db utility file path.
     * 
     * @param  string $filename The utility filename, or database name by default
     * @return string|false The file path where the utility file should be located
     */
    protected function getDbFile($filename = '')
    {
        $dir = path_root('db');
        if (!file_exists($dir)) {
            @mkdir($dir, 0777, true);
        }

        $sname = empty($filename) ? snake_case($this->name) : snake_case($filename);
        return "$dir/{$sname}.php";
    }

    /**
     * Creates a Db utility file if needed and returns the file pointer.
     * 
     * @param  string $filename The utility filename, or database name by default
     * @return resource|false A file pointer resource on success, or false on error
     */
    protected function createDbFile($filename = '')
    {
        $filepath = $this->getDbFile($filename);
        $res = fopen($filepath, 'w+');
        return $res;
    }
}
