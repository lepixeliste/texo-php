<?php

namespace Core;

/**
 * Base view template
 *
 * @version 1.0.0
 * @author Charlie LEDUC <contact@pixeliste.fr>
 */

class View
{
    /** @var string */
    protected $contents = '';

    /** @var array */
    protected $context = [];

    /** @var string */
    protected $filepath;

    /** @var string */
    protected $filename;

    /** @var string */
    public $charset;

    /** @var string */
    public $lang;

    /**
     * @param string $file The view template file location
     * @param array $context Any context variables to interpolate
     * @return void
     */
    public function __construct($file, $context = [])
    {
        $this->lang = 'en';
        $this->charset = getenv('APP_CHARSET');

        $filepath = path_root("views/$file");
        $this->filepath = dirname($filepath);
        $this->filename = basename($filepath);

        $contents = @file_get_contents($filepath);
        $this->contents = is_string($contents) ? $contents : '';
        $this->context = $context;
    }

    /**
     * Sets context variable
     * 
     * @param string $key
     * @param mixed $value
     * @return self
     */
    public function setContext($key, $value)
    {
        $this->context[$key] = $value;
        return $this;
    }

    /**
     * Renders the template HTML file as string
     *
     * @return string
     */
    public function render()
    {
        $ob_contents = $this->renderContents($this->contents);

        if ((bool)preg_match('/\<head/is', $ob_contents) && !(bool)preg_match('/\<\!doctype html/is', $ob_contents)) {
            $ob_contents = '<!doctype html>' . PHP_EOL . $ob_contents;
        }

        return trim($ob_contents);
    }

    /**
     * Gets the string representation of the view.
     *
     * @param  string $contents
     * @return string
     */
    protected function renderContents($contents)
    {
        $tmp = tmpfile();
        $metadata = stream_get_meta_data($tmp);
        $tmp_uri = $metadata['uri'];
        fwrite($tmp, $this->regex($contents));
        fseek($tmp, 0);

        $renderer = function ($__template_file, $__context_data) {
            // Make escaping functions available in templates
            $e = 'esc_html';
            $esc_html = 'esc_html';
            $esc_attr = 'esc_attr';
            $esc_js = 'esc_js';
            $esc_url = 'esc_url';

            // Extract only user-provided context with a whitelist approach
            // Prefix internal variables with __ to avoid conflicts
            foreach ($__context_data as $__key => $__value) {
                // Prevent overwriting critical variables
                if (in_array($__key, ['__template_file', '__context_data', '__key', '__value', 'e', 'esc_html', 'esc_attr', 'esc_js', 'esc_url'], true)) {
                    continue;
                }
                $$__key = $__value;
            }

            ob_start();
            include_once $__template_file;
            return ob_get_contents();
        };

        $context_with_defaults = array_merge([
            'lang' => $this->lang,
            'charset' => $this->charset
        ], $this->context);

        $ob_contents = $renderer($tmp_uri, $context_with_defaults);
        ob_end_clean();

        if (is_resource($tmp)) {
            fclose($tmp);
        }

        return trim($ob_contents);
    }

    /**
     * Gets the string representation of the view.
     *
     * @param  string $contents
     * @return string
     */
    protected function regex($contents)
    {
        $regexps = [
            ['reg' => '/\<\?[\s]+/m', 'rep' => '<?php ']
        ];
        foreach ($regexps as $regex) {
            $contents = preg_replace($regex['reg'], $regex['rep'], $contents);
        }

        $re_asset = '/asset\([\'"](.*?)[\'"]\)/m';
        $contents = preg_replace_callback($re_asset, function ($matches) {
            $asset_match = isset($matches[1]) ? $matches[1] : '';
            $asset_path = path_resolve(path_asset($asset_match));
            $host = $_SERVER['SERVER_NAME'] ?? 'localhost';
            $base_url = path_join($host, getenv('APP_BASE_URL') ?? '');
            $asset_path = str_replace(path_asset(), "//$base_url/assets", $asset_path);
            if (!is_env_prod()) {
                $time = time();
                $asset_path .= "?t=$time";
            }
            return "'$asset_path'";
        }, $contents);

        $re_include = '/\<\?php\s+include\([\'"](.*?)[\'"]\)\s+\?\>/m';
        $contents = preg_replace_callback($re_include, function ($matches) {
            $include_match = isset($matches[1]) ? $matches[1] : '';
            $include_contents = file_get_contents(path_resolve(path_join($this->filepath, $include_match)));
            $tmp_uri = $this->renderContents($include_contents);
            return $tmp_uri;
        }, $contents);

        $re_each = '/foreach\(\$(.*)\s+(?:as)/m';
        preg_match_all($re_each, $contents, $each_matches, PREG_SET_ORDER, 0);
        foreach ($each_matches as $match) {
            $each_match = isset($match[1]) ? $match[1] : '';
            if (!isset($this->context[$each_match]) || !is_array($this->context[$each_match])) {
                $this->context[$each_match] = [];
            }
        }

        return $contents;
    }

    /**
     * Gets the string representation of the view.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->render();
    }
}
