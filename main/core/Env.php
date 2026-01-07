<?php

namespace Core;

use Exception;
use InvalidArgumentException;
use RuntimeException;

/**
 * Class wrapper to load environment variables from .env files.
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class Env
{
    /**
     * Path where the .env file can be located.
     *
     * @var string
     */
    protected $path;

    /**
     * @param string $path .env file location
     * @return void
     * @throws \InvalidArgumentException if file does not exist at specified path
     */
    public function __construct(string $path)
    {
        if (!file_exists($path)) {
            throw new InvalidArgumentException(sprintf('%s does not exist', $path));
        }
        $this->path = $path;
    }

    /**
     * Loads with the default .env file.
     *
     * @return void
     */
    public static function boot()
    {
        $default_file = path_root('.env');
        if (!file_exists($default_file)) {
            $default_file = static::createFile();
        }
        $env_local = path_root('.env.local');
        $env_file = file_exists($env_local) ? $env_local : $default_file;
        $env = new Env($env_file);
        $env->load();
    }

    /**
     * Creates a new .env file.
     * 
     * @param string $name The env. name file
     * @return string
     * @throws \Exception if file could not be created
     */
    public static function createFile($name = '')
    {
        $filename = '.env';
        if (!empty($name)) {
            $filename .= '.' . strtolower($name);
        }
        $filepath = path_root($filename);
        $res = fopen($filepath, 'w+');
        if (!$res) {
            throw new Exception(sprintf('Could not open `%s`', $filepath));
        }

        $values = [
            [
                'APP_NAME' => '',
                'APP_ENV' => empty($name) ? 'production' : 'development',
                'APP_MEMORY_LIMIT' => '256M',
                'APP_VERSION' => '1.0.0',
                'APP_CHARSET' => 'utf-8',
                'APP_TIMEZONE' => date_default_timezone_get(),
                'APP_LOCALE' => '0',
                'APP_BASE_URL' => '/',
                'APP_STORAGE' => '/files',
            ],
            [
                'SQL_DB' => '',
                'SQL_HOST' => '127.0.0.1',
                'SQL_PORT' => 3306,
                'SQL_USER' => 'root',
                'SQL_PASS' => '',
                'SQL_CHARSET' => 'utf8mb4',
                'SQL_COLLATE' => 'utf8mb4_unicode_ci'
            ],
            [
                'SMTP_HOST' => '',
                'SMTP_PORT' => 465,
                'SMTP_EMAIL' => '',
                'SMTP_USER' => '',
                'SMTP_PASS' => '',
            ],
            [
                'SSL_PASSPHRASE' => uniqid()
            ],
            [
                'JWT_ALGO' => 'HS512',
                'JWT_KEY_USER' => uniqid()
            ]
        ];

        $string = implode(PHP_EOL, array_map(function ($a) {
            $s = '';
            foreach ($a as $k => $v) {
                $env_value = getenv($k);
                if ($env_value === false) {
                    $env_value = $v;
                }
                $s .= "$k=$env_value" . PHP_EOL;
            }
            return $s;
        }, $values));
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
     * Update a new .env file.
     * 
     * @param  array  $values  An array with key-value pairs
     * @param  string $name    The env. name file
     * @return int|false
     * @throws \Exception if file could not be update
     */
    public static function updateFile($values, $name = '')
    {
        $filename = '.env';
        if (!empty($name)) {
            $filename .= '.' . strtolower($name);
        }
        $filepath = path_root($filename);
        $file_contents = file_get_contents($filepath);
        if (!$file_contents) {
            throw new Exception(sprintf('Could not open `%s`', $filepath));
        }

        $chunks = array_map(function ($group) {
            return array_map(function ($row) {
                return explode('=', $row);
            }, explode(PHP_EOL, $group));
        }, preg_split('/\s{2,}/m', trim($file_contents)));

        $missing_keys = [];
        foreach ($values as $key => $value) {
            $f = false;
            foreach ($chunks as $i => $chunk) {
                foreach ($chunk as $j => $group) {
                    if (!isset($chunks[$i][$j][1])) {
                        continue;
                    }
                    if ($key !== $chunks[$i][$j][0]) {
                        continue;
                    }
                    $chunks[$i][$j][1] = $value;
                    $f = true;
                }
            }
            if (!$f) {
                $missing_keys[] = $key;
            }
        }

        $chunks[] = array_map(function ($missing_key) use ($values) {
            return [$missing_key, $values[$missing_key]];
        }, $missing_keys);

        $string = implode(PHP_EOL . PHP_EOL, array_map(function ($chunk) {
            return implode(PHP_EOL, array_map(function ($group) {
                return $group[0] . '=' . $group[1];
            }, $chunk));
        }, $chunks));
        return file_put_contents($filepath, $string);
    }

    /**
     * Loads any environment variables from the specified .env file.
     *
     * @return void
     */
    public function load()
    {
        if (!is_readable($this->path)) {
            throw new RuntimeException(sprintf('%s file is not readable', $this->path));
        }

        $lines = file($this->path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}
