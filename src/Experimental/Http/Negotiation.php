<?php
/*
 * Inphinit
 *
 * Copyright (c) 2019 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental\Http;

use Inphinit\Http\Request;
use Inphinit\Experimental\Exception;

class Negotiation
{
    private $headers;

    /** Sort values in the header low to high by q-factors */
    const LOW = 1;

    /** Sort values in the header high to low by q-factors */
    const HIGH = 2;

    /** Get all values from a accept header (without q-factor) */
    const ALL = 3;

    /**
     * Create a Negotiation instance
     *
     * @param array $headers This parameter is optional, you can set with
     *                       headers returned by curl or other way
     * @return void
     */
    public function __construct(array $headers = null)
    {
        $this->headers = array_change_key_case($headers ? $headers : Request::header(), CASE_LOWER);

        self::filter($this->headers);
    }

    /**
     * Get all languages by `Accept-Language` header sorted by q-factor (defined by `$level`)
     *
     * @param int $level Sorts languages using `LOW` or `HIGH` constants,
     *                   or return all in an simple array use `ALL` constant
     * @throws \Inphinit\Experimental\Exception
     * @return array|bool
     */
    public function acceptLanguage($level = self::HIGH)
    {
        return $this->header('accept-language', $level);
    }

    /**
     * Get all languages by `Accept-Charset` header and sort by q-factor (defined by `$level`)
     *
     * @param int $level Sorts charsets using `LOW` or `HIGH` constants,
     *                   or return all in an simple array use `ALL` constant
     * @throws \Inphinit\Experimental\Exception
     * @return array
     */
    public function acceptCharset($level = self::HIGH)
    {
        return $this->header('accept-charset', $level);
    }

    /**
     * Get all languages by `Accept-Encoding` header and sort by q-factor (defined by `$level`)
     *
     * @param string $level Sorts encodings using `LOW` or `HIGH` constants,
     *                      or return all in an simple array use `ALL` constant
     * @throws \Inphinit\Experimental\Exception
     * @return array|bool
     */
    public function acceptEncoding($level = self::HIGH)
    {
        return $this->header('accept-encoding', $level);
    }

    /**
     * Get all document types by `Accept` header and sorted by q-factor (defined by `$level`)
     *
     * @param int $level Sorts types using `LOW` or `HIGH` constants,
     *                   or return all in an simple array use `ALL` constant
     * @throws \Inphinit\Experimental\Exception
     * @return array|bool
     */
    public function accept($level = self::HIGH)
    {
        return $this->header('accept', $level);
    }

    /**
     * Get the first language with with the greatest q-factor,
     * if it does not exist then return the value of `$alternative`
     *
     * @param mixed $alternative Define alternative value, this value will be
     *                           used does not have the "header"
     * @throws \Inphinit\Experimental\Exception
     * @return mixed
     */
    public function getLanguage($alternative = false)
    {
        $headers = $this->acceptLanguage();
        return $headers ? key($headers) : $alternative;
    }

    /**
     * Get the first charset with with the greatest q-factor,
     * if it does not exist then return the value of `$alternative`
     *
     * @param mixed $alternative Define alternative value, this value will be
     *                           used does not have the "header"
     * @throws \Inphinit\Experimental\Exception
     * @return mixed
     */
    public function getCharset($alternative = false)
    {
        $headers = $this->acceptCharset();
        return $headers ? key($headers) : $alternative;
    }

    /**
     * Get the first encoding with with the greatest q-factor,
     * if it does not exist then return the value of `$alternative`
     *
     * @param mixed $alternative Define alternative value, this value will be
     *                           used does not have the "header"
     * @throws \Inphinit\Experimental\Exception
     * @return mixed
     */
    public function getEncoding($alternative = false)
    {
        $headers = $this->acceptEncoding();
        return $headers ? key($headers) : $alternative;
    }

    /**
     * Get the first "document type" with the greatest q-factor,
     * if it does not exist then return the value of `$alternative`
     *
     * @param mixed $alternative Define alternative value, this value will be
     *                           used does not have the "header"
     * @throws \Inphinit\Experimental\Exception
     * @return mixed
     */
    public function getAccept($alternative = false)
    {
        $headers = $this->accept();
        return $headers ? key($headers) : $alternative;
    }

    /**
     * Parse any header like `TE` header or headers with `Accepet-` prefix
     *
     * @param string $header
     * @param int    $level
     * @throws \Inphinit\Experimental\Exception
     * @return array|bool
     */
    public function header($header, $level = self::HIGH)
    {
        $header = strtolower($header);

        if (empty($this->headers[$header])) {
            return false;
        }

        return self::qFactor($this->headers[$header], $level);
    }

    /**
     * Parse and sort a custom value with q-factor
     *
     * @param string $value
     * @param int    $level
     * @throws \Inphinit\Experimental\Exception
     * @return array
     */
    public static function qFactor($value, $level = self::HIGH)
    {
        $multivalues = explode(',', $value);
        $headers = array();

        foreach ($multivalues as $hvalues) {
            if (substr_count($hvalues, ';') > 1) {
                throw new Exception('Header contains a value with multiple semicolons: "' . $value . '"', 2);
            }

            $current = explode(';', $hvalues, 2);

            if (empty($current[1])) {
               $qvalue = 1.0;
            } else {
                $qvalue = self::parseQValue($current[1]);
            }

            $headers[ trim($current[0]) ] = $qvalue;
        }

        $multivalues = null;

        if ($level === self::ALL) {
            return array_keys($headers);
        }

        if ($level === self::LOW) {
            asort($headers, SORT_NUMERIC);
        } else {
            arsort($headers, SORT_NUMERIC);
        }

        return $headers;
    }

    private static function filter(&$headers)
    {
        foreach ($headers as $key => &$value) {
            if ($key !== 'te' && $key !== 'accept-ranges' && strpos($key, 'accept-') === 0 && strpos($key, 'accept-control-') !== 0) {
                unset($value);
            }
        }
    }

    private static function parseQValue($value)
    {
        $qvalue = str_replace('q=', '', $value);

        if (is_numeric($qvalue) === false) {
            throw new Exception('Header contains a q-factor non numeric: "' . $value . '"', 3);
        } else if ($qvalue > 1) {
            throw new Exception('Header contains a q-factor greater than 1 (value of q parameter can be from 0.0 to 1.0): "' . $value . '"', 3);
        }

        return floatval($qvalue);
    }
}
