<?php
/*
 * Inphinit
 *
 * Copyright (c) 2019 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit;

class Storage
{
    /**
     * Get absolute path from storage location
     *
     * @return string
     */
    public static function path()
    {
        return INPHINIT_PATH . 'storage/';
    }

    /**
     * Convert path to storage path
     *
     * @param string $path
     * @return bool|string
     */
    public static function resolve($path)
    {
        if (empty($path)) {
            return false;
        }

        $path = Uri::canonpath($path);

        if ($path . '/' === self::path() || strpos($path, self::path()) === 0) {
            return $path;
        }

        return self::path() . $path;
    }

    /**
     * Clear old files in a folder from storage path
     *
     * @param string $path
     * @param int    $time
     * @return void
     */
    public static function autoclean($path, $time = -1)
    {
        $path = self::resolve($path);

        if ($path !== false && is_dir($path) && ($dh = opendir($path))) {
            if ($time < 0) {
                $time = App::env('appdata_expires');
            }

            $expires = REQUEST_TIME - $time;
            $path .= '/';

            while (false !== ($file = readdir($dh))) {
                $current = $path . $file;

                if (is_file($current) && filemtime($current) < $expires) {
                    unlink($current);
                }
            }

            closedir($dh);

            $dh = null;
        }
    }

    /**
     * Create a tmp in storage/tmp folder
     *
     * @param string $data
     * @param string $path
     * @param string $prefix
     * @param string $sulfix
     * @return bool|string
     */
    public static function temp($data = null, $path = 'tmp', $prefix = '~', $sulfix = '.tmp')
    {
        $fullpath = self::resolve($path);

        if ($fullpath === false) {
            return false;
        }

        $fullpath .= '/' . $prefix . base_convert(microtime(true), 10, 36);
        $fullpath .= rand(1, 1000) . $sulfix;

        if (is_file($fullpath) || self::put($fullpath, $data, LOCK_EX) === false) {
            return self::temp($data, $path, $prefix, $sulfix);
        }

        return $fullpath;
    }

    /**
     * Create a file in a folder or overwrite existing file
     *
     * @param string $path
     * @param string $data
     * @return bool
     */
    public static function write($path, $data = null)
    {
        self::put($path, $data, LOCK_EX);
    }

    /**
     * Create a file in a folder in storage or append data to existing file
     *
     * @param string $path
     * @param string $data
     * @param int    $flags
     * @return bool
     */
    public static function put($path, $data = null, $flags = null)
    {
        $path = self::resolve($path);

        if ($path === false) {
            return false;
        }

        $data = is_numeric($data) === false && !$data ? '' : $data;

        if (is_file($path) && !$data) {
            return true;
        }

        $flags = $flags ? $flags : FILE_APPEND|LOCK_EX;

        return self::createFolder(dirname($path)) && file_put_contents($path, $data, $flags) !== false;
    }

    /**
     * Delete a file in storage
     *
     * @param string $path
     * @return bool
     */
    public static function remove($path)
    {
        $path = self::resolve($path);

        return $path && is_file($path) && unlink($path);
    }

    /**
     * Create a folder in storage using 0700 permission (if unix-like)
     *
     * @param string $path
     * @return bool
     */
    public static function createFolder($path)
    {
        $path = self::resolve($path);

        return $path && (is_dir($path) || mkdir($path, 0700, true));
    }

    /**
     * Remove recursive folders in storage folder
     *
     * @param string $path
     * @return bool
     */
    public static function removeFolder($path)
    {
        $path = self::resolve($path);

        return $path && is_dir($path) && self::rrmdir($path);
    }

    /**
     * Remove recursive folders
     *
     * @param string $path
     * @return bool
     */
    private static function rrmdir($path)
    {
        $path .= '/';

        foreach (array_diff(scandir($path), array('..', '.')) as $file) {
            $current = $path . $file;

            if (is_dir($current)) {
                if (self::rrmdir($current) === false) {
                    return false;
                }
            } elseif (unlink($current) === false) {
                return false;
            }
        }

        return rmdir($path);
    }
}
