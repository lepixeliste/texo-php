<?php

namespace Core\Routing;

use Core\Http\Request;
use Core\Context;

/**
 * The Core Router.
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class Router
{
    /**
     * The routes.
     *
     * @var array<string,\Core\Routing\Route[]>
     */
    protected $routes = [];

    /**
     * Named routes for URL generation.
     *
     * @var array<string,\Core\Routing\Route>
     */
    protected $namedRoutes = [];

    /**
     * Gets all routes.
     * 
     * @return array<string,\Core\Routing\Route[]>
     */
    public function getRoutes()
    {
        return $this->routes;
    }

    /**
     * Defines a Route using GET method.
     *
     * @param  \Core\Routing\Route|string $path
     * @param  array $args
     * @return self
     */
    public function get($path, ...$args)
    {
        return $this->add('GET', $path, $args);
    }

    /**
     * Defines a Route using POST method.
     * 
     * @param  \Core\Routing\Route|string $path
     * @param  array $args
     * @return self
     */
    public function post($path, ...$args)
    {
        return $this->add('POST', $path, $args);
    }

    /**
     * Defines a Route using PATCH method.
     * 
     * @param  \Core\Routing\Route|string $path
     * @param  array $args
     * @return self
     */
    public function patch($path, ...$args)
    {
        return $this->add('PATCH', $path, $args);
    }

    /**
     * Defines a Route using PUT method.
     * 
     * @param  \Core\Routing\Route|string $path
     * @param  array $args
     * @return self
     */
    public function put($path, ...$args)
    {
        return $this->add('PUT', $path, $args);
    }

    /**
     * Defines a Route using DELETE method.
     * 
     * @param  \Core\Routing\Route|string $path
     * @param  array $args
     * @return self
     */
    public function delete($path, ...$args)
    {
        return $this->add('DELETE', $path, $args);
    }

    /**
     * Defines a Route using OPTIONS method.
     * 
     * @param  \Core\Routing\Route|string $path
     * @param  array $args
     * @return self
     */
    public function options($path, ...$args)
    {
        return $this->add('OPTIONS', $path, $args);
    }

    /**
     * Defines a Route using HEAD method.
     * 
     * @param  \Core\Routing\Route|string $path
     * @param  array $args
     * @return self
     */
    public function head($path, ...$args)
    {
        return $this->add('HEAD', $path, $args);
    }

    /**
     * Defines a Route for all available methods.
     *
     * @param  \Core\Routing\Route|string $path
     * @param  array $args
     * @return self
     */
    public function all($path, ...$args)
    {
        return $this->add('ALL', $path, $args);
    }

    /**
     * Add a new Route to the list.
     *
     * @param  string $method
     * @param  \Core\Routing\Route|string $path
     * @param  array $args
     * @return self
     */
    protected function add($method, $path, $args)
    {
        $middleware = count($args) > 1 ? $args[0] : null;
        $callback = count($args) > 1 ? $args[1] : (count($args) > 0 ? $args[0] : null);
        $name = count($args) > 2 ? $args[2] : null;

        $route = $path instanceof Route ? $path : new Route($path, $callback, $name);
        if ($middleware !== null) {
            $route->middleware($middleware);
        }
        $this->registerRoute($route, $method);
        return $this;
    }

    /**
     * Registers a Route if not already present.
     *
     * @param  \Core\Routing\Route $route
     * @param  string $method
     * @return void
     */
    protected function registerRoute(Route $route, $method)
    {
        if (!isset($this->routes[$method])) {
            $this->routes[$method] = [];
        }

        $path = $route->getPath();
        if (empty($path)) {
            return;
        }

        $route_exists = false;
        foreach ($this->routes[$method] as $existing_route) {
            if ($path === $existing_route->getPath()) {
                $route_exists = true;
                break;
            }
        }

        if (!$route_exists) {
            $this->routes[$method][] = $route;
            // Register named route for URL generation
            $name = $route->getName();
            if ($name !== null) {
                $this->namedRoutes[$name] = $route;
            }
        }
    }

    /**
     * Returns the fitting response from the incoming request.
     *
     * @param  \Core\Http\Request $request
     * @param  \Core\Context $context
     * @return ResponseInterface
     * @throws \Core\Routing\RouterException|\Core\Routing\RouteException
     */
    public function dispatch(Request $request, Context $context)
    {
        $method = $request->getMethod();

        if (isset($this->routes[$method])) {
            $base_url = getenv('APP_BASE_URL') ?? '';
            foreach ($this->routes[$method] as $route) {
                if (!$route->match($request, $base_url)) {
                    continue;
                }
                $response = $route->call($request, $context);
                if ($response->getStatusCode() === 100) {
                    continue;
                }
                return $response;
            }
        }

        if (isset($this->routes['ALL'])) {
            foreach ($this->routes['ALL'] as $route) {
                if ($route->match($request)) {
                    return $route->call($request, $context);
                }
            }
        }

        throw new RouterException(sprintf('No matching route for `%s`', $request->getRequestTarget()));
    }

    /**
     * Generates a URL for a named route.
     *
     * @param  string $name
     * @param  array $params
     * @return string
     * @throws \Core\Routing\RouterException
     */
    public function url($name, array $params = [])
    {
        if (!isset($this->namedRoutes[$name])) {
            throw new RouterException(sprintf('Named route `%s` does not exist.', $name));
        }

        $route = $this->namedRoutes[$name];
        $path = $route->getPath();

        // Replace route parameters with provided values
        foreach ($params as $key => $value) {
            $path = preg_replace('#:' . preg_quote($key, '#') . '#', $value, $path, 1);
        }

        // Check if there are any unreplaced parameters
        if (preg_match('#:[a-zA-Z0-9_]+#', $path)) {
            throw new RouterException(sprintf('Missing parameters for route `%s`.', $name));
        }

        return $path;
    }
}
