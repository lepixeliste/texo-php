<?php

namespace Core\Cli\Commands;

use ArrayIterator;
use Exception;
use Phar;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Core\Auth\SSL;

/**
 * CmdPhar
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class CmdPhar extends Cmd
{
    const FILENAME = 'app';

    public function params()
    {
        return [
            'build' => 'Build a new Phar file',
            'extract' => 'Unarchive the Phar file to update the current framework',
        ];
    }

    public function invoke($param, $args, $options)
    {
        $this->printer->newline();

        switch ($param) {
            case 'build': {
                    try {
                        $this->printer
                            ->out('{bgcyan} PHAR {nc} v' . Phar::apiVersion());

                        $can_compress = Phar::canCompress(Phar::GZ);
                        $compress = (isset($options['-c']) || isset($options['--compress']));
                        if ($compress && !$can_compress) {
                            $this->printer
                                ->out("{yellow}phar:$param{nc} > Cannot compress to GZ format, ignoring step.");
                        }

                        $filename = $can_compress && $compress ? static::FILENAME . '.gz.phar' : static::FILENAME . '.phar';
                        $path_root = path_root();
                        $phar_path = path_root($filename);

                        if (file_exists($phar_path)) {
                            unlink($phar_path);
                        }
                        if (file_exists($phar_path . '.pubkey')) {
                            unlink($phar_path . '.pubkey');
                        }

                        $phar = new Phar($phar_path, 0, $filename);

                        $signed = (isset($options['-s']) || isset($options['--signed']));
                        if ($signed) {
                            $key = SSL::generate();
                            if ($key !== false) {
                                $phar->setSignatureAlgorithm(Phar::OPENSSL, $key['private']);
                                file_put_contents($phar_path . '.pubkey', $key['public']);
                            }
                        }

                        $phar->startBuffering();

                        $regexps = [
                            '(?:index.php$)|^(?:cli$)',
                            '(?:main\/\w+.php$)',
                            '(?:main\/core\/.*(?:.php|.tmp)$)',
                            '(?:main\/tasks\/.*?_boot.php)'
                        ];
                        if (isset($options['-a']) || isset($options['--app'])) {
                            $regexps[] = '(?:main\/app\/.*(?:.php|.tmp)$)';
                        }
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

                        $paths = $phar->buildFromIterator(new ArrayIterator($files));
                        if (empty($paths)) {
                            $this->printer
                                ->out("{red}phar:$param{nc} > No files to build from.");
                            break;
                        }

                        $phar->setStub($phar->createDefaultStub('cli', 'index.php'));
                        $phar->stopBuffering();

                        if ($can_compress && $compress) {
                            $phar->compressFiles(Phar::GZ);
                        }

                        chmod($phar_path, 0770);

                        $message = $can_compress && $compress ? 'successfully created and compressed' : 'successfully created';
                        $this->printer
                            ->out("{green}phar:$param{nc} > `{italic}$phar_path{nc}` $message.");
                    } catch (Exception $e) {
                        $error = $e->getMessage();
                        $this->printer
                            ->out("{red}phar:$param{nc} > $error");
                    }
                    break;
                }
            case 'extract': {
                    $this->printer
                        ->out('{bgcyan} PHAR {nc} v' . Phar::apiVersion());

                    try {
                        $extract_to = path_root('');
                        $phar_c_path = path_root(static::FILENAME . '.gz.phar');
                        $phar_u_path = path_root(static::FILENAME . '.phar');
                        $is_compressed = file_exists($phar_c_path);
                        $phar_path = $is_compressed ? $phar_c_path : $phar_u_path;
                        if (!file_exists($phar_path)) {
                            $this->printer
                                ->out("{red}phar:$param{nc} > Could not locate `{italic}$phar_path{nc}`");
                            break;
                        }

                        $phar = new Phar($phar_path, 0, $is_compressed ? static::FILENAME . '.gz.phar' : static::FILENAME . '.phar');
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
}
