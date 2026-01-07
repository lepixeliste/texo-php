<?php

namespace Core\Cli;

/**
 * Convenient wrapper to format terminal output.
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class Printer
{
    /** Styles dictionnary */
    const STYLES = [
        'nc' => 0,

        'bold' => 1,
        'italic' => 3,
        'underline' => 4,
        'inverse' => 7,

        'black' => 30,
        'red' => 31,
        'green' => 32,
        'yellow' => 33,
        'blue' => 34,
        'purple' => 35,
        'cyan' => 36,
        'white' => 37,

        'bgblack' => 40,
        'bgred' => 41,
        'bggreen' => 42,
        'bgyellow' => 43,
        'bgblue' => 44,
        'bgpurple' => 45,
        'bgcyan' => 46,
        'bgwhite' => 47
    ];

    /**
     * Prints a new line.
     * 
     * @param  int $n Number of lines created
     * @return self
     */
    public function newline($n = 1)
    {
        if (php_sapi_name() === 'cli') {
            echo implode('', array_fill(0, $n, "\n"));
        }
        return $this;
    }

    /**
     * Prints the message.
     * 
     * @param  string $message Any string to echo
     * @return self
     */
    public function out($message)
    {
        if (php_sapi_name() !== 'cli') {
            return $this;
        }

        echo $this->style($message);
        return $this->newline();
    }

    /**
     * Prompts the user any message.
     * 
     * @param  string $prompt Any string to prompt
     * @param  bool   $silent
     * @return string|false
     */
    public function ask($prompt, $silent = false)
    {
        if (php_sapi_name() !== 'cli') {
            return false;
        }

        $message = $this->style($prompt);
        if ($silent && (PHP_OS !== 'WINNT' || PHP_OS !== 'WIN32')) {
            echo $message;
            $s = exec('read -s PW; echo $PW');
            echo PHP_EOL;
            return $s;
        }
        return readline($message);
    }

    /**
     * Style the console message.
     * 
     * @param  string $message
     * @return string
     */
    private function style($message)
    {
        $re = '/\{([a-zA-Z0-9;]+)\}/s';
        preg_match_all($re, $message, $matches, PREG_SET_ORDER, 0);
        if (count($matches) > 0) {
            foreach ($matches as $match) {
                $match_style = isset($match[1]) ? $match[1] : '0';
                $styles = explode(';', $match_style);
                $re_style = implode(';', array_filter(array_map(function ($style) {
                    return isset(static::STYLES[$style]) ? static::STYLES[$style] : (is_numeric($style) ? $style : '0');
                }, $styles), function ($s) {
                    return !empty($s);
                }));
                $message = preg_replace("/\{$match_style\}/s", "\033[{$re_style}m", $message);
            }
        }
        return preg_replace($re, '', $message);
    }
}
