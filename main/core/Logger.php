<?php

namespace Core;

use JsonSerializable;
use InvalidArgumentException;
use Core\Psr\Log\AbstractLogger;
use Core\Psr\Log\LogLevel;

/**
 * For any logging operations.
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class Logger extends AbstractLogger
{
    /**
     * The timestamp format.
     *
     * @var string
     */
    const TIMESTAMP_FORMAT = DATE_W3C;

    /**
     * @param mixed  $level
     * @param string|\Stringable $message
     * @param array  $context
     *
     * @see log
     */
    public static function print($level, string|\Stringable $message, array $context = []): void
    {
        $new = new static;
        $new->log($level, $message, $context);
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed  $level
     * @param string|\Stringable $message
     * @param array  $context
     * @return void
     * @throws \InvalidArgumentException
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $levels = [
            LogLevel::ALERT,
            LogLevel::CRITICAL,
            LogLevel::DEBUG,
            LogLevel::EMERGENCY,
            LogLevel::ERROR,
            LogLevel::INFO,
            LogLevel::NOTICE,
            LogLevel::WARNING
        ];
        if (!in_array($level, $levels)) {
            throw new InvalidArgumentException('Invalid log level');
        }

        $dir = path_root('logs');
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }

        $filename = "{$dir}/{$level}.log";
        $res = fopen($filename, 'a+');
        if (!$res) {
            throw new InvalidArgumentException(sprintf('Unable to open log file `%s`', $filename));
        }

        $timestamp = date(static::TIMESTAMP_FORMAT);
        $strval = $this->interpolate($message, $context);
        $string = "[$timestamp] $strval" . PHP_EOL;
        $fwrite = 0;
        for ($written = 0; $written < strlen($string); $written += $fwrite) {
            $fwrite = fwrite($res, substr($string, $written));
            if ($fwrite === false || $fwrite < 1) {
                break;
            }
        }

        fclose($res);
    }

    /**
     * Interpolates all occurrences from context.
     *
     * @param  string $message The string to interpolate
     * @param  array  $context The context variables
     * @return string
     */
    protected function interpolate($message, array $context = [])
    {
        $replace = [];
        foreach ($context as $key => $val) {
            $arg = $val instanceof JsonSerializable ? $val->jsonSerialize() : $val;
            $strval = is_array($arg) || is_object($arg) ? print_r($arg, true) : strval($arg);
            $replace['{' . $key . '}'] = $strval;
        }

        return strtr($message, $replace);
    }
}
