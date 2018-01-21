<?php
/*
* Inphinit
*
* Copyright (c) 2018 Guilherme Nascimento (brcontainer@yahoo.com.br)
*
* Released under the MIT license
*/

function ini_enabled($key)
{
    $value = strtolower(ini_get($key));
    return in_array($value, array( 'on', '1' ));
}

$definedpath = defined('INPHINIT_PATH');

$error = array();
$warn  = array();

if (version_compare(PHP_VERSION, '5.3.0', '<')) {
    $error[] = 'Requires PHP5.3 or major, your current version of PHP is ' . PHP_VERSION;
}

if ($definedpath) {
    $folder = INPHINIT_PATH . 'storage';

    if (!is_writable($folder)) {
        $error[] = 'Folder ' . $folder . ' requires write permissions, use chmod';
    }
}

if (function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc()) {
    $error[] = 'magic_quotes_gpc is enabled';
}

if (!function_exists('mb_detect_encoding')) {
    $error[] = 'Inphinit\Uri class and Inphinit\Helper::toAscii not work, ' .
                '"Multibyte String" is disabled in php (if needed for you)';
}

if (!function_exists('iconv')) {
    $error[] = 'Inphinit\Uri not work, "iconv" is disabled in php (if needed for you)';
}

if (!function_exists('finfo_file')) {
    $error[] = 'Class Inphinit\Files (mimeType method) not work, ' .
                     '"finfo" is disabled in php (if needed for you)';
}

if ($definedpath) {
    $systemConfigs = require INPHINIT_PATH . 'application/Config/config.php';

    if ($systemConfigs['developer'] === false) {
        if (extension_loaded('xdebug')) {
            $warn[] = 'xdebug is enabled, is recommended disable this in "production mode"';
        }

        if (extension_loaded('xhprof')) {
            $warn[] = 'xhprof is enabled, is recommended disable this in "production mode"';
        }

        $generators = array(
            'generate-htaccess.php',
            'generate-webconfig.php',
            'generate-nginx.php'
        );

        foreach ($generators as $generator) {
            if (is_file($generator)) {
                $error[] = 'Remove ' . $generator . ' in production server';
            }
        }
    } else {
        if (function_exists('xcache_get') && ini_enabled('xcache.cacher')) {
            $warn[] = 'Disable xcache.cacher in dev mode';
        }

        if (function_exists('opcache_get_status') && ini_enabled('opcache.enable')) {
            $warn[] = 'Disable opcache.enable in dev mode';
        }

        if (function_exists('wincache_ocache_meminfo') && ini_enabled('wincache.ocenabled')) {
            $warn[] = 'Disable wincache.ocenabled in dev mode';
        }

        if (function_exists('apc_compile_file') && ini_enabled('apc.enabled')) {
            $warn[] = 'Disable apc.ocenabled in dev mode';
        }

        if (function_exists('eaccelerator_get') && ini_enabled('eaccelerator.enable')) {
            $warn[] = 'Disable eaccelerator.ocenabled in dev mode';
        }
    }
}

return (object) array(
    'error' => $error,
    'warn'  => $warn
);
