<?php

namespace Core;

use Exception;
use RuntimeException;
use Core\Context;
use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Router;
use Core\Psr\Log\LogLevel;

/**
 * The main app instance.
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class App
{
    /**
     * The App Session ID.
     *
     * @var string
     */
    protected $sessionId;

    /**
     * The App main router.
     *
     * @var \Core\Routing\Router
     */
    protected $router;

    /**
     * The App service context.
     *
     * @var \Core\Context
     */
    protected $context;

    /**
     * The Http original request.
     *
     * @var \Core\Http\Request|null
     */
    protected $request;

    /**
     * An array of origins for header 'Access-Control-Allow-Origin'.
     *
     * @var array<string>
     */
    protected $allowed_origins;

    /**
     * @param \Core\Routing\Router $router
     * @param \Core\Context $context
     * @return void
     */
    public function __construct(Router $router, Context $context)
    {
        $this->sessionId = uniqid();
        $this->router = $router;
        $this->context = $context;
        $this->allowed_origins = [];
    }

    /**
     * The app router.
     * 
     * @return \Core\Routing\Router $router
     */
    public function router()
    {
        return $this->router;
    }

    /**
     * The app context.
     * 
     * @return \Core\Context $context
     */
    public function context()
    {
        return $this->context;
    }

    /**
     * Loads from any given Http Request.
     * 
     * @param  \Core\Http\Request $request The initial request
     * @return void
     */
    public function boot(Request $request)
    {
        $env_origins = getenv('ALLOWED_ORIGINS');
        $this->allowed_origins = !$env_origins || empty($env_origins) ? [] : array_map('trim', explode(',', $env_origins));
        $this->request = $request;
        $this->registerRoutes();
    }

    /**
     * Calls task jobs.
     * 
     * @param  callable $callable Optional anonymous function called when a job is completed  
     * @param  boolean  $latest_only Runs latest task only
     * @return array
     */
    public function task($callable = null, $latest_only = false)
    {
        $messages = [];
        $path = path_main('tasks');
        if (!file_exists($path)) {
            @mkdir($path, 0777, true);
        }

        $donefile = "$path/.done";
        if (!file_exists($donefile)) {
            file_put_contents($donefile, '');
        }
        $done_names = file($donefile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$done_names) {
            $done_names = [];
        }

        $paths = glob($path . '/*.php');
        if ($paths === false) {
            throw new RuntimeException("Unable to scan `$path`");
        }

        sort($paths);
        $paths = array_values(
            array_filter($paths, function ($item) use ($done_names) {
                $base_name = basename($item);
                return !in_array($base_name, $done_names);
            })
        );
        $tc = count($paths);
        foreach ($paths as $p => $path_name) {
            set_time_limit(100);
            if ($latest_only === true && $p < $tc - 1) {
                continue;
            }

            $fn = include_once $path_name;
            $base_name = basename($path_name);
            if (is_callable($fn)) {
                try {
                    $recurring = $fn($this->context);
                    if (!$recurring) {
                        $done_names[] = $base_name;
                    }
                    $messages[] = ["$base_name completed.", 1];
                    if (is_callable($callable)) {
                        $callable("$base_name completed.", 1);
                    }
                } catch (Exception $e) {
                    $error_msg = $e->getMessage();
                    $messages[] = ["$base_name => $error_msg", 0];
                    if (is_callable($callable)) {
                        $callable("$base_name => $error_msg", 0);
                    }
                    continue;
                }
            }
        }
        if (empty($paths)) {
            $messages[] = ['No new task to complete.', 1];
            if (is_callable($callable)) {
                $callable('No new task to complete.', 1);
            }
        }

        file_put_contents($donefile, implode("\r\n", array_filter($done_names, function ($item) {
            return !empty($item);
        })));
        return $messages;
    }

    /**
     * Registers routes from the main routes folder.
     * 
     * @return void
     * @throws \Exception
     */
    protected function registerRoutes()
    {
        $path = path_main('routes');
        $paths = glob($path . '/*.php');

        if ($paths === false) {
            throw new RuntimeException("Unable to scan `$path`");
        }

        foreach ($paths as $path_name) {
            $fn = include_once $path_name;
            if (is_callable($fn)) {
                $fn($this->router, $this);
            }
        }
    }

    /**
     * Echoes the Http response.
     * 
     * @return void
     */
    public function send()
    {
        $server_origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if (in_array('*', $this->allowed_origins, true)) {
            header("Access-Control-Allow-Origin: *");
        } else if (in_array($server_origin, $this->allowed_origins)) {
            header("Access-Control-Allow-Origin: $server_origin");
            header("Access-Control-Allow-Credentials: true");
            header("Vary: Origin");
        }

        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE');
        header('Access-Control-Allow-Headers: X-Requested-With, Content-Type, Origin, Cache-Control, Pragma, Authorization, authorization, Accept, Accept-Encoding, X-CSRF-Token, x-csrf-token, X-XSRF-Token, x-xsrf-token');
        header("Set-Cookie: sessionid={$this->sessionId}; SameSite=None; Secure");
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'self'; frame-ancestors 'none'; script-src 'self'");

        if (!($this->request instanceof Request)) {
            $e = new RuntimeException('Error processing Request', 1);
            $response = response(500)->json(['error' => $e->getMessage()]);
            header($response->contentType(), true, $response->getStatusCode());
            echo json_encode($response->data());
            exit;
        }


        if ($this->request->getMethod() == 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        try {
            $response = $this->router->dispatch($this->request, $this->context);
            if (!($response instanceof Response)) {
                $e = new RuntimeException('Error processing Response', 1);
                $response = response(500)->json(['error' => $e->getMessage()]);
                header($response->contentType(), true, $response->getStatusCode());
                echo json_encode($response->data());
                exit;
            }

            switch ($response->responseType()) {
                case 'redirect': {
                        $scheme = is_env_prod() || is_secure() ? 'https' : 'http';
                        $location = $response->location();
                        $message = $response->getReasonPhrase();
                        $protocol = $response->getProtocolVersion();
                        header("HTTP/$protocol $message");
                        header("Location: $scheme://$location");
                        exit;
                    }
                case 'stream': {
                        header($response->contentType(), true, $response->getStatusCode());
                        header('Content-Disposition: attachment');
                        $stream = fopen('php://temp', 'r+');
                        fwrite($stream, $response->data());
                        rewind($stream);
                        echo stream_get_contents($stream);
                        fclose($stream);
                        exit;
                    }
                case 'chunked': {
                        header($response->contentType(), true, $response->getStatusCode());
                        header('Transfer-Encoding: chunked');
                        echo '';
                        exit;
                    }
                case 'transfer': {
                        $attachment = $response->attachment();
                        header('Content-Description: File Transfer', true, $response->getStatusCode());
                        header($response->contentType());
                        header(sprintf('Content-Disposition: attachment; filename="%s"', $attachment->filename));
                        header('Expires: 0');
                        header('Cache-Control: must-revalidate');
                        header('Pragma: public');
                        header('Content-Length: ' . $attachment->filesize());
                        ob_end_clean();
                        readfile($attachment->filepath);
                        exit;
                    }
                case 'json': {
                        header($response->contentType(), true, $response->getStatusCode());
                        echo json_encode($response->data());
                        exit;
                    }
                default: {
                        header($response->contentType(), true, $response->getStatusCode());
                        echo $response->data();
                        exit;
                    }
            }
        } catch (Exception $e) {
            $response = response(500)->json(['status' => 500, 'message' => 'Internal Server Error.']);
            $message = $e->getMessage();
            Logger::print(LogLevel::ERROR, "'{$message}' in " . $e->getFile() . ':' . $e->getLine() . PHP_EOL . $e->getTraceAsString());
            header($response->contentType(), true, $response->getStatusCode());
            echo json_encode($response->data());
            exit;
        }
    }

    /**
     * Traces memory usage for the current request.
     * 
     * @return void
     */
    private function traceMemory()
    {
        $method = $this->request->getMethod();
        $uri = $this->request->getFullPath();
        $mem_usage = convert_bytes(memory_get_usage());
        $peak_usage = convert_bytes(memory_get_peak_usage());
        $clock = clock(true);

        $message = [
            "[$method]: '$uri'",
            $clock,
            "Memory allocated $mem_usage | Peak Memory allocated $peak_usage",
        ];

        Logger::print(LogLevel::INFO, implode(PHP_EOL, $message));
    }
}
