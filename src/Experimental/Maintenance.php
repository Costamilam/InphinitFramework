<?php
/*
 * Inphinit
 *
 * Copyright (c) 2016 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental;

use Inphinit\App;
use Inphinit\AppData;

class Maintenance
{
    /**
     * Down site to maintenance mode
     *
     * @return boolean
     */
    public static function down()
    {
        return self::enable(true);
    }

    /**
     * Up site
     *
     * @return void
     */
    public static function up()
    {
        return self::enable(false);
    }

    /**
     * Enable/disable maintenance mode
     *
     * @param $enable
     * @return boolean
     */
    protected static function enable($enable)
    {
        $data = include INPHINIT_PATH . 'application/Config/config.php';

        if ($data['maintenance'] === $enable) {
            return null;
        }

        $data['maintenance'] = $enable;

        $wd = preg_replace('#,(\s+|)\)#', '$1)', var_export($data, true));

        $path = AppData::createTmp('<?php' . EOL . 'return ' . $wd . ';' . EOL);

        $response = copy($path, INPHINIT_PATH . 'application/Config/config.php');

        unlink($path);

        return $response;
    }

    /**
     * Up the site only in certain conditions, eg. the site administrator of the IP.
     *
     * @callable $enable
     * @return void
     */
    public static function ignoreif($callback)
    {
        if (is_callable($callback) === false) {
            Exception::raise('Invalid callback');
        }

        App::on('init', function() use ($callback) {
            if ($callback()) {
                App::env('maintenance', false);
            }
        });
    }
}