<?php

namespace Core\Routing;

use Closure;
use Core\Collection;
use Core\Http\Request;
use Core\Context;
use Core\Psr\Http\Message\ResponseInterface;

/**
 * The Core Route.
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class Route
{
    private const ALLOWED_CHARS = '[a-zA-Z0-9_\.,;:\+\-\*\|]+';

    /**
     * The route path.
     *
     * @var string
     */
    protected $path = '';

    /**
     * The route name.
     *
     * @var string|null
     */
    protected $name;

    /**
     * The route callable.
     *
     * @var mixed
     */
    protected $callable;

    /**
     * The route middleware.
     *
     * @var \Core\Routing\MiddlewareInterface|string|null
     */
    protected $middleware;

    /**
     * The route resolved params.
     *
     * @var string[]
     */
    protected $params = [];

    /**
     * Check if the route is resolved.
     *
     * @var bool
     */
    protected $resolved = false;

    /**
     * Parameter constraints.
     *
     * @var array<string,string>
     */
    protected $constraints = [];

    /**
     * @param  string $path
     * @param  callable|array $callable Any callback function
     *      or an array of string representing a Controller class with the method to call
     * @param  string|null $name An optional route name
     * @return void
     */
    public function __construct($path, $callable, $name = null)
    {
        $this->path = is_string($path) ? $path : '';
        $this->name = $name;
        $this->callable = $callable;
        $this->params = [];
    }

    /**
     * Gets the route path.
     *
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Gets the route name.
     *
     * @return string|null
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Gets the route resolved params.
     *
     * @return string[]
     */
    public function params()
    {
        return $this->params;
    }

    /**
     * The route path, if resolved.
     *
     * @return string
     */
    public function resolvedPath()
    {
        if (!$this->resolved) {
            return $this->path;
        }

        $resolved_path = $this->path;
        foreach ($this->params as $param) {
            $resolved_path = preg_replace('#:(' . static::ALLOWED_CHARS . ')#', $param, $resolved_path, 1);
        }
        return $resolved_path;
    }

    /**
     * Gets the route middleware if any or sets it if filled.
     *
     * @param  \Core\Routing\MiddlewareInterface|string|null $middleware
     * @return \Core\Routing\MiddlewareInterface|string|null|self
     */
    public function middleware($middleware = null)
    {
        if (isset($middleware)) {
            $this->middleware = $middleware;
            return $this;
        }

        return $this->middleware;
    }

    /**
     * Checks if the incoming request matches the route pattern.
     *
     * @param  \Core\Http\Request $request
     * @param  string $prepend
     * @return bool
     */
    public function match(Request $request, $prepend = '')
    {
        $this->params = [];
        $this->resolved = true;

        $ds = DIRECTORY_SEPARATOR;
        $route_path = trim(preg_replace('/\/{2,}/', $ds, $ds . $prepend . $ds . $this->path), "\n\r\t\v\x00\x2F\x5C");

        try {
            $compiled = RouteCompiler::compile($route_path);
        } catch (RouteException $e) {
            return false;
        }

        $uri = $request->getUri()->getPath();
        // Add timeout protection for regex matching
        set_error_handler(function () {});
        $matches_result = @preg_match_all($compiled['pattern'], $uri, $uri_matches, PREG_SET_ORDER, 0);
        restore_error_handler();

        if ($matches_result === false || count($uri_matches) < 1) {
            return false;
        }

        $uri_matches = $uri_matches[0];
        $cm = count($uri_matches);

        if ($cm > 0) {
            for ($i = 1; $i < $cm; $i++) {
                $uri_match = $uri_matches[$i];
                if ($uri_match !== $uri) {
                    $this->params[] = $uri_match;
                }
            }

            // Handle optional parameters
            $pattern_string = str_replace('#^/?', '', $compiled['pattern']);
            $pattern_string = str_replace('$#', '', $pattern_string);
            preg_match_all('#\(.*\)\?#', $pattern_string, $cond_matches, PREG_SET_ORDER, 0);
            $cm = count($cond_matches);
            $cp = count($this->params);
            if ($cm > $cp) {
                $delta = abs($cm - $cp);
                for ($i = 0; $i < $delta; $i++) {
                    $this->params[] = null;
                }
            }
        }

        return true;
    }

    /**
     * Resolves the incoming request in current context.
     *
     * @param  \Core\Http\Request $request
     * @param  \Core\Context $context
     * @return ResponseInterface
     * @throws \Core\Routing\RouteException
     */
    public function call(Request $request, Context $context): ResponseInterface
    {
        if (!$this->resolved) {
            throw new RouteException(sprintf('Route `%s` has not been resolved yet.', $this->path));
        }

        $response = $this->invokeMiddleware($request, function ($request) use ($context) {
            return $this->invokeCallable($request, $context);
        });

        // Use ResponseHandler for type conversion
        return ResponseHandler::normalize($response);
    }

    /**
     * Invokes the callback function to perform and returns a suitable response.
     * 
     * @param  \Core\Http\Request $request
     * @param  \Core\Context $context
     * @return ResponseInterface
     * @throws \Core\Routing\RouteException
     */
    protected function invokeCallable(Request $request, Context $context)
    {
        $fparams = count($this->params) > 0 ? [...$this->params, $request] : [$request];
        $callable = $this->callable;
        if (is_callable($callable)) {
            return $callable(...$fparams);
        }

        if (is_array($callable) && count($callable) === 2) {
            $class_name = $callable[0];
            $fn_name = $callable[1];
            $controller = new $class_name($context);
            if (!method_exists($controller, $fn_name)) {
                throw new RouteException(sprintf('Method `%s` does not exist in controller `%s`.', $fn_name, $class_name));
            }
            return call_user_func([$controller, $fn_name], ...$fparams);
        }

        return response();
    }

    /**
     * Filters the incoming request with the invoked middleware.
     *
     * @param  \Core\Http\Request $request
     * @param  \Closure $next
     * @return ResponseInterface
     * @throws \Core\Routing\RouteException
     */
    protected function invokeMiddleware(Request $request, Closure $next)
    {
        if (isset($this->middleware)) {
            if ($this->middleware instanceof MiddlewareInterface) {
                return $this->middleware->process($request, $next);
            } elseif (is_string($this->middleware)) {
                if (!class_exists($this->middleware)) {
                    throw new RouteException(sprintf('Middleware class `%s` does not exist.', $this->middleware));
                }
                $middleware = new $this->middleware;
                if (!($middleware instanceof MiddlewareInterface)) {
                    throw new RouteException(sprintf('Middleware `%s` must implement MiddlewareInterface.', $this->middleware));
                }
                return $middleware->process($request, $next);
            }
        }
        return $next($request);
    }

    /**
     * Returns the route as string.
     * 
     * @return string
     */
    public function __toString()
    {
        return $this->path;
    }
}
