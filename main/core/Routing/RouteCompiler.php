<?php

namespace Core\Routing;

/**
 * Compiles route patterns into optimized regex for matching.
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class RouteCompiler
{
    private const ALLOWED_CHARS = '[a-zA-Z0-9_\.,;:\+\-\*\|]+';

    /**
     * Cache for compiled routes.
     *
     * @var array
     */
    private static $cache = [];

    /**
     * Compiles a route path into a regex pattern.
     *
     * @param  string $path
     * @return array{pattern: string, variables: array}
     * @throws \Core\Routing\RouteException
     */
    public static function compile($path)
    {
        // Check cache first
        if (isset(self::$cache[$path])) {
            return self::$cache[$path];
        }

        $variables = [];
        $re_params = '#:(' . self::ALLOWED_CHARS . ')#';
        $re_match = '#:match\((.+)\)#U';

        $has_match = (bool)preg_match($re_match, $path);

        // Validate custom regex patterns
        if ($has_match) {
            preg_match($re_match, $path, $custom_pattern);
            if (isset($custom_pattern[1])) {
                set_error_handler(function() {});
                $is_valid = @preg_match('#' . $custom_pattern[1] . '#', '') !== false;
                restore_error_handler();

                if (!$is_valid) {
                    throw new RouteException(sprintf('Invalid regex pattern in route: %s', $path));
                }
            }
        }

        // Extract variable names
        preg_match_all($re_params, $path, $var_matches);
        if (!empty($var_matches[1])) {
            $variables = $var_matches[1];
        }

        // Build regex pattern
        $pattern = $has_match
            ? preg_replace($re_match, '$1', $path)
            : preg_replace($re_params, '(' . self::ALLOWED_CHARS . ')', $path);

        $compiled = [
            'pattern' => '#^/?' . $pattern . '$#',
            'variables' => $variables
        ];

        // Cache the compiled route
        self::$cache[$path] = $compiled;

        return $compiled;
    }

    /**
     * Clears the compilation cache.
     *
     * @return void
     */
    public static function clearCache()
    {
        self::$cache = [];
    }
}
