<?php

namespace Core\Cli\Commands;

use ArrayIterator;
use Exception;
use PharData;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * CmdPhar
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class CmdTar extends Cmd
{
    const FILENAME = 'app';

    public function params()
    {
        return [
            'build' => 'Build a new .tar file',
            'extract' => 'Unarchive the .tar file to update the current framework',
        ];
    }

    public function invoke($param, $args, $options)
    {
        $this->printer->newline();

        switch ($param) {
            case 'build': {
                    $filename = static::FILENAME . '.tar';
                    $tar_path = path_root($filename);

                    if (file_exists($tar_path)) {
                        unlink($tar_path);
                    }

                    try {
                        $phar = new PharData($tar_path);
                        $iterator = $this->buildIterator();
                        $phar->buildFromIterator($iterator);
                        $this->printer
                            ->out("{green}phar:$param{nc} > `{italic}$tar_path{nc}` successfully created.");
                    } catch (Exception $e) {
                        $error = $e->getMessage();
                        $this->printer
                            ->out("{red}phar:$param{nc} > $error");
                    }
                    break;
                }
            case 'extract': {
                    try {
                        $extract_to = path_root('');
                        $filename = static::FILENAME . '.tar';
                        $tar_path = path_root($filename);
                        if (!file_exists($tar_path)) {
                            $this->printer
                                ->out("{red}phar:$param{nc} > Could not locate `{italic}$tar_path{nc}`");
                            break;
                        }

                        $phar = new PharData($filename);
                        $phar->extractTo($extract_to, null, true);
                        $this->printer
                            ->out("{green}phar:$param{nc} > Done extracting to `$extract_to`.");
                    } catch (Exception $e) {
                        $error = $e->getMessage();
                        $this->printer
                            ->out("{red}phar:$param{nc} > $error");
                    }
                    break;
                }
            default: {
                    $this->printer
                        ->out("{bgred;bold} ERROR {nc} {italic}phar(:parameter) [argument?]{nc} -> invalid {yellow}(:parameter){nc} argument.");
                    break;
                }
        }

        $this->printer->newline();
    }

    private function buildIterator()
    {
        $path_root = path_root();

        $regexps = [
            '(?:index.php$)|^(?:cli$)',
            '(?:main\/\w+.php$)',
            '(?:main\/core\/.*(?:.php|.tmp)$)',
            '(?:main\/app\/.*(?:.php|.tmp)$)',
            '(?:main\/routes\/\w+.php$)',
            '(?:main\/tasks\/.*?_boot.php)',
        ];
        $regexp = '/^' . implode('|', $regexps) . '/i';

        $files = [];
        $directory = new RecursiveDirectoryIterator($path_root);
        $iterator = new RecursiveIteratorIterator($directory);
        foreach ($iterator as $info) {
            $path_name = $info->getPathname();
            if (!$info->isFile()) {
                continue;
            }
            $key_path = trim(str_replace($path_root, '', $path_name), DIRECTORY_SEPARATOR);
            if ((bool)preg_match($regexp, $key_path)) {
                $files[$key_path] = $path_name;
            }
        }

        return new ArrayIterator($files);
    }
}
