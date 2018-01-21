<?php
/*
 * Inphinit
 *
 * Copyright (c) 2018 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Http;

class Request
{
    private static $reqHeaders;
    private static $reqHeadersLower;

    /**
     * Get current HTTP path or route path
     *
     * @param bool $info
     * @return string
     */
    public static function path($info = false)
    {
        return $info ? \UtilsPath() : parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    }

    /**
     * Check if is a specific HTTP method, HTTPS, and xmlhttprequest (Depends on how an ajax call was made)
     *
     * @param string $check
     * @return bool
     */
    public static function is($check)
    {
        switch ($check) {
            case 'secure':
                return empty($_SERVER['HTTPS']) === false && strcasecmp($_SERVER['HTTPS'], 'on') === 0;

            case 'xhr':
                return strcasecmp(self::header('X-Requested-With'), 'xmlhttprequest') === 0;

            case 'pjax':
                return strcasecmp(self::header('X-Pjax'), 'true') === 0;
        }

        return strcasecmp($_SERVER['REQUEST_METHOD'], $check) === 0;
    }

    /**
     * Get HTTP headers from current request
     *
     * @param string $name
     * @return string|array|bool
     */
    public static function header($name = null)
    {
        if (self::$reqHeaders === null) {
            self::generate();
        }

        if (is_string($name)) {
            $name = strtolower($name);
            return isset(self::$reqHeadersLower[$name]) ? self::$reqHeadersLower[$name] : false;
        }

        return self::$reqHeaders;
    }

    /**
     * Get querystring, this method is useful for anyone who uses IIS.
     *
     * @return string|bool
     */
    public static function query()
    {
        if (empty($_GET['RESERVED_IISREDIRECT']) === false) {
            return false;
        }

        return isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : false;
    }

    /**
     * Get a value from `$_GET`, if `$_GET` is a array multidimensional, you can use dot like path:
     * If `$_GET['foo']` returns this `array( 'baz' => 'bar' => 1);` use `Request::get('foo.bar.baz');`
     * for return `1`
     *
     * @param string $key
     * @param mixed  $alternative
     * @return mixed
     */
    public static function get($key, $alternative = false)
    {
        return self::data($_GET, $key, $alternative);
    }

    /**
     * Get a value from $_POST, if $_POST is a array multidimensional, you can use dot like path:
     * If $_POST['foo'] returns this array( 'baz' => 'bar' => 1); use Request::post('foo.bar.baz');
     *
     * @param string $key
     * @param mixed  $alternative
     * @return mixed
     */
    public static function post($key, $alternative = false)
    {
        return self::data($_POST, $key, $alternative);
    }

    /**
     * Get a value from `$_COOKIE` (support path using dots)
     *
     * @param string $key
     * @param mixed  $alternative
     * @return mixed
     */
    public static function cookie($key, $alternative = false)
    {
        return self::data($_COOKIE, $key, $alternative);
    }

    /**
     * Get a value from `$_FILES` (support path using dots)
     *
     * @param string $key
     * @return mixed
     */
    public static function file($key)
    {
        $pos = strpos($key, '.');
        $firstKey = $key;
        $restKey  = null;

        if ($pos > 0) {
            $firstKey = substr($key, 0, $pos);
            $restKey  = substr($key, $pos + 1);
        } elseif (isset($_FILES[$firstKey]['name']) === false) {
            return false;
        } elseif ($restKey === null) {
            return $_FILES[$firstKey];
        }

        $tmpName = Helper::extract($restKey, $_FILES[$firstKey]['tmp_name']);

        if ($tmpName === false) {
            return false;
        }

        return array(
            'tmp_name' => $tmpName,
            'name'     => Helper::extract($restKey, $_FILES[$firstKey]['name']),
            'type'     => Helper::extract($restKey, $_FILES[$firstKey]['type']),
            'error'    => Helper::extract($restKey, $_FILES[$firstKey]['error']),
            'size'     => Helper::extract($restKey, $_FILES[$firstKey]['size'])
        );
    }

    /**
     * Get a value input handler
     *
     * @param bool $binary
     * @return resource|bool
     */
    public static function raw($binary = true)
    {
        if (is_readable('php://input')) {
            return false;
        }

        $mode = $binary ? 'rb' : 'r';

        if (PHP_VERSION_ID >= 50600) {
            return fopen('php://input', $mode);
        }

        $tmp = Storage::temp();

        return copy('php://input', $tmp) ? fopen($tmp, $mode) : false;
    }

    private static function data(&$data, $key, $alternative = false)
    {
        if (empty($data)) {
            return $alternative;
        } elseif (strpos($key, '.') === false) {
            return isset($data[$key]) ? $data[$key] : $alternative;
        }

        $data = Helper::extract($key, $data);
        return $data === false ? $alternative : $data;
    }

    private static function generate()
    {
        $headers = array();

        if (function_exists('getallheaders')) {
            $headers = getallheaders();
        } else {
            foreach ($_SERVER as $key => $value) {
                if (strpos($key, 'HTTP_') === 0) {
                    $current = Helper::capitalize(substr($key, 5), '_', '-');
                    $headers[$current] = $value;
                }
            }
        }

        self::$reqHeaders = $headers;
        self::$reqHeadersLower = array_change_key_case($headers, CASE_LOWER);

        $headers = null;
    }
}