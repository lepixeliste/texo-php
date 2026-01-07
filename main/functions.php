<?php

/**
 * functions.php
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

define('APP_BOOT', microtime(true));

if (!function_exists('unique_id')) {
    /**
     * Generates a md5-hashed unique id.
     *
     * @param int $l id generated length
     * @return string
     */
    function unique_id($l = 32)
    {
        return substr(md5(uniqid(mt_rand(), true)), 0, $l);
    }
}

if (!function_exists('random_string')) {
    /**
     * Generates a random string with at least 1 uppercase, 1 lowercase and 1 special character.
     *
     * @param int $l string length
     * @return string
     */
    function random_string($l = 8)
    {
        $len = max(6, $l);
        $lowercases = 'abcdefghijklmnopqrstuvwxyz';
        $uppercases = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $numbers = '0123456789';
        $specials = '!@#$%^&*()_+-=[]{}|;:,.<>?';
        $all = $lowercases . $uppercases . $numbers . $specials;

        $str = '';
        $str .= $lowercases[random_int(0, strlen($lowercases) - 1)];
        $str .= $uppercases[random_int(0, strlen($uppercases) - 1)];
        $str .= $numbers[random_int(0, strlen($numbers) - 1)];
        $str .= $specials[random_int(0, strlen($specials) - 1)];
        for ($i = 4; $i < $len; $i++) {
            $str .= $all[random_int(0, strlen($all) - 1)];
        }
        return str_shuffle($str);
    }
}

if (!function_exists('is_primitive')) {
    /**
     * Checks if the given value is either bool, numeric or string.
     *
     * @param mixed $value Any value to check
     * @return bool
     */
    function is_primitive($value)
    {
        return is_bool($value) || is_numeric($value) || is_string($value);
    }
}

if (!function_exists('get_value')) {
    /**
     * Gets value of an object or array by key.
     *
     * @param string $key Key to lookup
     * @param mixed $data Object or Array
     * @param mixed $if_null return a default value if not found
     * @return mixed
     */
    function get_value($key, $data, $if_null = null)
    {
        if (is_array($data)) {
            return array_key_exists($key, $data) ? $data[$key] : $if_null;
        } elseif (is_object($data)) {
            if (method_exists($data, '__get')) {
                $val = $data->__get($key);
                return isset($val) ? $val : $if_null;
            }
            return property_exists($data, $key) ? $data->{$key} : $if_null;
        }

        return $if_null;
    }
}

if (!function_exists('array_index')) {
    /**
     * Gets the index of the first element in the array that satisfies the provided testing function.
     *
     * @param array $array An array
     * @param callable $callable The callback function to use
     * @return int
     */
    function array_index(array $array, ?callable $callable)
    {
        if (!is_callable($callable)) {
            return -1;
        }
        $c = count($array);
        for ($i = 0; $i < $c; $i++) {
            if ($callable($array[$i], $i)) {
                return $i;
            }
        }
        return -1;
    }
}

if (!function_exists('array_is_list')) {
    function array_is_list(array $arr)
    {
        if ($arr === []) {
            return true;
        }
        return array_keys($arr) === range(0, count($arr) - 1);
    }
}

if (!function_exists('get_referer')) {
    /**
     * Gets server Http referer if set.
     *
     * @return array|false|null
     */
    function get_referer()
    {
        return isset($_SERVER['HTTP_REFERER']) ? parse_url($_SERVER['HTTP_REFERER']) : false;
    }
}

if (!function_exists('get_http_origin')) {
    /**
     * Gets server Http origin if set.
     *
     * @return array|false|null
     */
    function get_http_origin()
    {
        return $_SERVER['HTTP_ORIGIN'] ?? false;
    }
}

if (!function_exists('get_request_url')) {
    /**
     * Gets server request url.
     *
     * @return string
     */
    function get_request_url()
    {
        $protocol = is_secure() ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . $host . $_SERVER['REQUEST_URI'];
    }
}

if (!function_exists('get_request_method')) {
    /**
     * Gets server request method.
     *
     * @return string
     */
    function get_request_method()
    {
        return $_SERVER['REQUEST_METHOD'];
    }
}

if (!function_exists('get_ip')) {
    /**
     * Gets the client IP address securely.
     *
     * By default, only trusts REMOTE_ADDR to prevent IP spoofing.
     * If behind a trusted proxy, set TRUSTED_PROXY_IPS in .env (comma-separated list).
     *
     * @param bool $trust_proxy Whether to check forwarded headers from trusted proxies
     * @return string|null
     */
    function get_ip($trust_proxy = true)
    {
        // Always trust REMOTE_ADDR as it cannot be spoofed by the client
        $remote_addr = $_SERVER['REMOTE_ADDR'] ?? null;

        if (!$remote_addr || !filter_var($remote_addr, FILTER_VALIDATE_IP)) {
            return null;
        }

        // If not using trusted proxies, return REMOTE_ADDR directly
        if (!$trust_proxy) {
            return $remote_addr;
        }

        // Check if REMOTE_ADDR is a trusted proxy
        $trusted_proxies = get_trusted_proxy_ips();
        if (empty($trusted_proxies) || !in_array($remote_addr, $trusted_proxies, true)) {
            // Not behind a trusted proxy, return REMOTE_ADDR
            return $remote_addr;
        }

        // We're behind a trusted proxy, check X-Forwarded-For
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $forwarded_ips = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));

            // Get the rightmost IP (closest to our server, most trustworthy)
            // The chain goes: client -> proxy1 -> proxy2 -> our server
            // X-Forwarded-For: client, proxy1, proxy2
            // We want the rightmost non-trusted IP
            for ($i = count($forwarded_ips) - 1; $i >= 0; $i--) {
                $ip = $forwarded_ips[$i];

                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }

                // Also accept private IPs if explicitly trusted
                if (filter_var($ip, FILTER_VALIDATE_IP) && !in_array($ip, $trusted_proxies, true)) {
                    return $ip;
                }
            }
        }

        // Fallback to REMOTE_ADDR if no valid forwarded IP found
        return $remote_addr;
    }
}

if (!function_exists('get_trusted_proxy_ips')) {
    /**
     * Gets the list of trusted proxy IP addresses from environment.
     *
     * Set TRUSTED_PROXY_IPS in .env as a comma-separated list.
     * Example: TRUSTED_PROXY_IPS=10.0.0.1,172.16.0.1,192.168.1.1
     *
     * @return array
     */
    function get_trusted_proxy_ips()
    {
        $env_proxies = getenv('TRUSTED_PROXY_IPS');

        if (!$env_proxies || empty($env_proxies)) {
            return [];
        }

        $proxies = array_map('trim', explode(',', $env_proxies));

        // Validate all IPs
        return array_filter($proxies, function ($ip) {
            return filter_var($ip, FILTER_VALIDATE_IP);
        });
    }
}

if (!function_exists('is_secure')) {
    /**
     * Checks if server is using https.
     *
     * @return bool
     */
    function is_secure()
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
    }
}

if (!function_exists('is_env_prod')) {
    /**
     * Checks if the app is in production mode.
     *
     * @return bool
     */
    function is_env_prod()
    {
        return getenv('APP_ENV') === 'production';
    }
}

if (!function_exists('is_localhost')) {
    /**
     * Checks if server address is localhost / 127.0.0.1 / ::1
     *
     * @return bool
     */
    function is_localhost()
    {
        if (!isset($_SERVER['REMOTE_ADDR'])) return true;
        $whitelist = array('127.0.0.1', '::1');
        return in_array($_SERVER['REMOTE_ADDR'], $whitelist);
    }
}

if (!function_exists('path_join')) {
    /**
     * Joins paths and trim leading slash.
     *
     * @param array $args expanded path array
     * @return string
     */
    function path_join(...$args)
    {
        $paths = array_map(function ($e) {
            return is_null($e) ? '' : strval($e);
        }, $args);
        $path = implode(DIRECTORY_SEPARATOR, array_filter($paths, function ($e) {
            return is_string($e) && !empty($e);
        }));
        $chars = "\n\r\t\v\x00\x2F\x5C";
        return trim(preg_replace('/\/{2,}/', DIRECTORY_SEPARATOR, $path), $chars);
    }
}

if (!function_exists('path_resolve')) {
    /**
     * Resolves a sequence of paths or path segments into an absolute path.
     *
     * @param string $args A sequence of paths or path segments
     * @return string
     */
    function path_resolve(...$args)
    {
        $paths = path_join(...$args);
        $path = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $paths);
        $parts = array_filter(explode(DIRECTORY_SEPARATOR, $path), 'strlen');
        $paths = [];
        foreach ($parts as $part) {
            if ('.' === $part) continue;
            if ('..' === $part) {
                array_pop($paths);
            } else {
                $paths[] = $part;
            }
        }
        return DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $paths);
    }
}

if (!function_exists('storage_url')) {
    /**
     * Gets a storage url set by APP_STORAGE environment key.
     *
     * @param array $args expanded path array
     * @return string
     */
    function storage_url(...$paths)
    {
        $env_storage = getenv('APP_STORAGE') ?? '';
        // Check if $env_storage starts with http:// or https://
        if (preg_match('/^https?:\/\//i', $env_storage)) {
            return rtrim($env_storage, '/') . '/' . path_join(...$paths);
        }

        // Otherwise, construct the URL with scheme and host
        $scheme = is_env_prod() || is_secure() ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host . '/' . path_join($env_storage, ...$paths);
    }
}

if (!function_exists('path_main')) {
    /**
     * Gets an absolute path from the main folder.
     *
     * @param array $args expanded path array
     * @return string
     */
    function path_main(...$paths)
    {
        return dirname(__FILE__) . '/' . path_join(...$paths);
    }
}

if (!function_exists('path_root')) {
    /**
     * Gets an absolute path from the root folder.
     *
     * @param array $args expanded path array
     * @return string
     */
    function path_root(...$paths)
    {
        return dirname(__DIR__, 1) . '/' . path_join(...$paths);
    }
}

if (!function_exists('path_asset')) {
    /**
     * Gets an absolute path from the assets folder.
     *
     * @param array $args expanded path array
     * @return string
     */
    function path_asset(...$paths)
    {
        return path_root('assets', ...$paths);
    }
}

if (!function_exists('log')) {
    /**
     * Logs any events of interest.
     *
     * @param string|\Stringable $message
     * @param array $context
     * @return void
     */
    function log(string|\Stringable $message, array $context = [])
    {
        $logger = new Core\Logger();
        $logger->info($message, $context);
    }
}

if (!function_exists('debug')) {
    /**
     * Logs any debugging value.
     *
     * @param array $args any value expanded array
     * @return void
     */
    function debug(...$args)
    {
        $logger = new Core\Logger();
        foreach ($args as $arg) {
            $message = $arg instanceof \JsonSerializable ? $arg->jsonSerialize() : $arg;
            $logger->debug(is_array($message) || is_object($message) ? print_r($message, true) : strval($message));
        }
    }
}

if (!function_exists('collect')) {
    /**
     * Creates a new Collection instance for the given array.
     *
     * @param array $items An optional array of items
     * @return \Core\Collection
     */
    function collect($items = [])
    {
        return new Core\Collection($items);
    }
}

if (!function_exists('response')) {
    /**
     * Creates a new Response instance for the given status code.
     *
     * @param array $items An optional Http status code
     * @return \Core\Http\Response
     */
    function response($code = 200)
    {
        return new Core\Http\Response($code);
    }
}

if (!function_exists('appversion')) {
    /**
     * Gets the semantic versioning value.
     *
     * @return string
     */
    function appversion()
    {
        return getenv('APP_VERSION');
    }
}

if (!function_exists('pascal_case')) {
    /**
     * Converts the given string to pascal case.
     *
     * @param string $str Any given string
     * @return string
     */
    function pascal_case($str)
    {
        if (!is_string($str)) {
            return '';
        }
        $split = preg_split('/([_\-\s+])/', $str);
        return $split !== false ? trim(
            implode('', array_map(function ($s) {
                return ucwords(strtolower($s));
            }, $split))
        ) : '';
    }
}

if (!function_exists('snake_case')) {
    /**
     * Converts the given string to snake case.
     *
     * @param string $str Any given string
     * @return string
     */
    function snake_case($str)
    {
        return is_string($str) ? trim(
            strtolower(
                preg_replace(
                    '/\s+/',
                    '_',
                    preg_replace('/(?<!^)[A-Z]/', '_$0', $str)
                )
            )
        ) : '';
    }
}

if (!function_exists('current_timestamp')) {
    /**
     * Gets the current timestamp as Y-m-d H:i:s.
     *
     * @return string
     */
    function current_timestamp()
    {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('hex2rgb')) {
    /**
     * Convert any hexadecimal color to RGB values.
     *
     * @param string $hex Any hexadecimal color
     * @return array<int,int,int>
     */
    function hex2rgb($hex)
    {
        if (!is_string($hex)) {
            $hex = '#000000';
        }

        $hex = preg_replace('/\#/', '', $hex);
        list($r, $g, $b) = array_map('hexdec', str_split($hex, 2));
        return [
            max(0, min(255, $r)),
            max(0, min(255, $g)),
            max(0, min(255, $b))
        ];
    }
}

if (!function_exists('rgb2hex')) {
    /**
     * Convert any RGB values to hexadecimal color.
     *
     * @param array<int,int,int>
     * @return string
     */
    function rgb2hex($rgb)
    {
        return '#' . implode('', array_map(
            function ($v) {
                return str_pad($v, 2, '0', STR_PAD_LEFT);
            },
            array_map(
                'dechex',
                array_map(function ($v) {
                    return max(0, min(255, $v));
                }, $rgb)
            )
        ));
    }
}

if (!function_exists('clock')) {
    /**
     * Gets script execution time in seconds at the given moment.
     *
     * @param bool $as_string Return as string instead of float
     * @return float|string
     */
    function clock($as_string = false)
    {
        $then = APP_BOOT;
        $now = microtime(true);
        $diff = abs($now - $then);
        if ($as_string) {
            $time = round($diff, 6);
            return "Process executed in {$time}s";
        }
        return $diff;
    }
}

if (!function_exists('convert_bytes')) {
    /**
     * Converts bytes to a more readable unit.
     *
     * @param int $bytes
     * @return string
     */
    function convert_bytes($bytes)
    {
        $unit = ['b', 'kb', 'mb', 'gb', 'tb', 'pb'];
        $i = floor(log($bytes, 1024));
        return round($bytes / pow(1024, $i), 2) . $unit[$i];
    }
}

if (!function_exists('get_auth_bearer')) {
    /**
     * Gets the authorization bearer token from current request, if any.
     *
     * @return string|false
     */
    function get_auth_bearer()
    {
        $apache_headers = function_exists('apache_request_headers') ? apache_request_headers() : false;
        if (!$apache_headers) {
            return false;
        }

        $auth_header = isset($apache_headers['authorization']) ? $apache_headers['authorization']
            : (isset($apache_headers['Authorization']) ? $apache_headers['Authorization'] : '');
        $bearer = is_string($auth_header) ? trim(preg_replace('/(bearer)/i', '', $auth_header)) : '';
        return !empty($bearer) ? $bearer : false;
    }
}

if (!function_exists('get_csrf_token')) {
    /**
     * Gets the CSRF Token from current request, if any.
     *
     * @return string|false
     */
    function get_csrf_token()
    {
        $apache_headers = function_exists('apache_request_headers') ? apache_request_headers() : false;
        if (!$apache_headers) {
            return false;
        }

        $csrf_token = isset($apache_headers['X-CSRF-Token']) ? $apache_headers['X-CSRF-Token']
            : (isset($apache_headers['x-csrf-token']) ? $apache_headers['x-csrf-token'] : '');
        return !empty($csrf_token) ? $csrf_token : false;
    }
}

if (!function_exists('getimageinfo')) {
    /**
     * Get image information from data
     *
     * @param string $data A string containing the image data.
     * @return array
     */
    function getimageinfo($data)
    {
        preg_match('/^data:image\/([a-z]+);base64,(.+)$/', $data, $matches);
        $ext = isset($matches[1]) ? strtolower($matches[1]) : null;
        return [
            'data' => isset($matches[2]) ? base64_decode($matches[2]) : false,
            'type' => $ext === 'jpeg' ? 'jpg' : $ext
        ];
    }
}

if (!function_exists('imageresampledfromstring')) {
    /**
     * Resamples an image from data
     *
     * @param string $data A string containing the image data.
     * @param string $filename The path to save the file to.
     * @param int $threshold When to resample from size threshold.
     * @return bool
     */
    function imageresampledfromstring($data, $filename, $threshold = 1280)
    {
        if (!function_exists('imagecreatefromstring')) {
            return false;
        }

        if (!isset($data) || empty($data) || !isset($filename) || empty($filename)) {
            return false;
        }

        $img_source = imagecreatefromstring($data);
        $w = imagesx($img_source);
        $h = imagesy($img_source);

        if ($w > $threshold) {
            $ratio = $threshold / $w;
            $nh = round($ratio * $h);
            $img_resampled = imagecreatetruecolor($threshold, $nh);
            imagecopyresampled($img_resampled, $img_source, 0, 0, 0, 0, $threshold, $nh, $w, $h);
            $s = imagejpeg($img_resampled, $filename);
            return $s;
        }

        $s = imagejpeg($img_source, $filename);
        return $s;
    }
}

if (!function_exists('validate_uploaded_file')) {
    /**
     * Validates an uploaded file for security.
     *
     * @param array $file The uploaded file from $_FILES
     * @param array $options Validation options:
     *                       - allowed_types: array of allowed MIME types (default: images only & pdf)
     *                       - max_size: maximum file size in bytes (default: 10MB)
     * @return array ['valid' => bool, 'error' => string|null, 'info' => array]
     */
    function validate_uploaded_file($file, $options = [])
    {
        // Default options
        $defaults = [
            'allowed_types' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'],
            'max_size' => 10 * 1024 * 1024 // 10MB
        ];
        $options = array_merge($defaults, $options);

        // Check if file was uploaded
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['valid' => false, 'error' => 'File was not uploaded properly.', 'info' => []];
        }

        // Check for upload errors
        if (isset($file['error']) && $file['error'] !== UPLOAD_ERR_OK) {
            $error_messages = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive.',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive.',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                UPLOAD_ERR_EXTENSION => 'File upload stopped by extension.'
            ];
            $error = isset($error_messages[$file['error']]) ? $error_messages[$file['error']] : 'Unknown upload error.';
            return ['valid' => false, 'error' => $error, 'info' => []];
        }

        // Check file size
        if (isset($file['size']) && $file['size'] > $options['max_size']) {
            return ['valid' => false, 'error' => 'File size exceeds maximum allowed size.', 'info' => []];
        }

        // Validate MIME type using finfo (not client-provided type)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);

        if ($options['allowed_types'] !== '*/*' && !in_array($mime_type, $options['allowed_types'], true)) {
            return ['valid' => false, 'error' => 'File type not allowed.', 'info' => ['mime' => $mime_type]];
        }

        // Validate file extension
        $filename = isset($file['name']) ? $file['name'] : '';
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        // Additional validation for images
        if (strpos($mime_type, 'image/') === 0) {
            $image_info = @getimagesize($file['tmp_name']);
            if ($image_info === false) {
                return ['valid' => false, 'error' => 'Invalid image file.', 'info' => []];
            }

            return [
                'valid' => true,
                'error' => null,
                'info' => [
                    'mime_type' => $mime_type,
                    'extension' => $extension,
                    'size' => $file['size'],
                    'width' => $image_info[0],
                    'height' => $image_info[1]
                ]
            ];
        }

        return [
            'valid' => true,
            'error' => null,
            'info' => [
                'mime_type' => $mime_type,
                'extension' => $extension,
                'size' => $file['size']
            ]
        ];
    }
}

if (!function_exists('generate_secure_filename')) {
    /**
     * Generates a cryptographically secure random filename.
     *
     * @param string $extension File extension (without dot)
     * @param int $length Length of random part (default: 32)
     * @return string
     */
    function generate_secure_filename($extension, $length = 32)
    {
        $random = bin2hex(random_bytes($length / 2));
        $extension = preg_replace('/[^a-z0-9]/i', '', $extension);
        return $random . '.' . $extension;
    }
}

if (!function_exists('generate_csrf_token')) {
    /**
     * Generates a cryptographically secure CSRF token.
     *
     * @return string
     */
    function generate_csrf_token()
    {
        return bin2hex(random_bytes(32));
    }
}

if (!function_exists('esc_html')) {
    /**
     * Escapes HTML entities to prevent XSS attacks.
     *
     * @param mixed $text The text to escape
     * @return string The escaped text
     */
    function esc_html($text)
    {
        if ($text === null) {
            return '';
        }

        if (is_array($text) || is_object($text)) {
            return htmlspecialchars(json_encode($text), ENT_QUOTES | ENT_HTML5, 'UTF-8', true);
        }

        return htmlspecialchars((string)$text, ENT_QUOTES | ENT_HTML5, 'UTF-8', true);
    }
}

if (!function_exists('esc_attr')) {
    /**
     * Escapes text for use in HTML attributes.
     *
     * @param mixed $text The text to escape
     * @return string The escaped text
     */
    function esc_attr($text)
    {
        if ($text === null) {
            return '';
        }

        return htmlspecialchars((string)$text, ENT_QUOTES | ENT_HTML5, 'UTF-8', true);
    }
}

if (!function_exists('esc_js')) {
    /**
     * Escapes text for use in JavaScript strings.
     *
     * @param mixed $text The text to escape
     * @return string The escaped text (without quotes)
     */
    function esc_js($text)
    {
        if ($text === null) {
            return '';
        }

        if (is_array($text) || is_object($text)) {
            return json_encode($text, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);
        }

        $text = (string)$text;

        // Escape common JavaScript characters
        $escape_chars = [
            '\\' => '\\\\',
            "'" => "\\'",
            '"' => '\\"',
            "\n" => '\\n',
            "\r" => '\\r',
            "\t" => '\\t',
            '<' => '\\x3C',  // Prevent </script> injection
            '>' => '\\x3E',
            '&' => '\\x26'
        ];

        return strtr($text, $escape_chars);
    }
}

if (!function_exists('esc_url')) {
    /**
     * Sanitizes a URL for safe output.
     *
     * @param string $url The URL to sanitize
     * @return string The sanitized URL
     */
    function esc_url($url)
    {
        if (empty($url)) {
            return '';
        }

        $url = (string)$url;

        // Remove any whitespace
        $url = trim($url);

        // Prevent javascript: and data: protocols
        if (preg_match('/^(javascript|data|vbscript):/i', $url)) {
            return '';
        }

        // Use filter_var for basic URL validation
        $filtered = filter_var($url, FILTER_SANITIZE_URL);

        return htmlspecialchars($filtered, ENT_QUOTES | ENT_HTML5, 'UTF-8', true);
    }
}

if (!function_exists('e')) {
    /**
     * Alias for esc_html() - short and convenient.
     *
     * @param mixed $text The text to escape
     * @return string The escaped text
     */
    function e($text)
    {
        return esc_html($text);
    }
}
