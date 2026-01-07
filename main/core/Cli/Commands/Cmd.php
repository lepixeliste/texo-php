<?php

namespace Core\Cli\Commands;

use Core\Cli\Printer;

/**
 * Cmd
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

abstract class Cmd
{
    /** @var \Core\Cli\Printer */
    protected $printer;

    /** 
     * @param \Core\Cli\Printer $printer
     * @return void
     */
    public function __construct(Printer $printer)
    {
        $this->printer = $printer;
    }

    /** 
     * Defines the command line names as key with value that provides help information.
     * @return array<string,string> 
     */
    public function params()
    {
        return [];
    }

    /** 
     * Outputs the command line with the associated arguments and parameter. 
     * 
     * @param  string   $param    The command parameter
     * @param  string[] $args     The command arguments
     * @param  string[] $options  The command options
     * @return void 
     */
    public function invoke($param, $args, $options)
    {
        $this->printer->newline();
    }

    /** 
     * Performs regular expressions search and replace on the generated file. 
     * 
     * @param  string $contents The contents of the file
     * @param  string $filename The name of the file
     * @return string 
     */
    protected function regReplace($contents, $filename)
    {
        $regexs = [
            ['/%_NAME_%/', $filename],
            ['/%_VERSION_%/', getenv('APP_VERSION')]
        ];
        foreach ($regexs as $regex) {
            $contents = preg_replace($regex[0], $regex[1], $contents);
        }

        return $contents;
    }
}
