<?php
/*
 * Inphinit
 *
 * Copyright (c) 2019 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

namespace Inphinit\Experimental\Routing;

use Inphinit\App;
use Inphinit\Routing\Route;
use Inphinit\Routing\Router;
use Inphinit\Experimental\Exception;
use Inphinit\Experimental\Dom\Document;

class Resource extends Router
{
    private static $debuglvl = 2;
    private $path = '';
    private $fullController;
    private $ready = false;
    private static $valids = array(
        'index'   => array( 'GET',  '/' ),
        'create'  => array( 'GET',  '/create' ),
        'store'   => array( 'POST', '/' ),
        'show'    => array( 'GET',  '/{:[^/]+:}' ),
        'edit'    => array( 'GET',  '/{:[^/]+:}/edit' ),
        'update'  => array( 'POST', '/{:[^/]+:}/update' ),
        'destroy' => array( 'POST', '/{:[^/]+:}/destroy' ),
    );

    /**
     * Create REST routes based in a \Controller
     *
     * @param string $controller
     * @return void
     */
    public static function create($controller)
    {
        self::$debuglvl = 3;

        $rest = new static($controller);
        $rest->prepare();
        $rest = null;
    }

    /**
     * Create REST routes based in a \Controller
     *
     * @param string $controller
     * @throws \Inphinit\Experimental\Exception
     * @return \Inphinit\Experimental\Rest
     */
    public function __construct($controller)
    {
        $fullController = '\\Controller\\' . parent::$prefixNS . strtr($controller, '.', '\\');

        if (class_exists($fullController) === false) {
            $level = self::$debuglvl;

            self::$debuglvl = 2;

            throw new Exception('Invalid class ' . $fullController, $level);
        }

        $this->fullController = $fullController;

        $path = strtolower(preg_replace('#([a-z])([A-Z])#', '$1-$2', strtr($controller, '.', '/')));

        $this->path = '/' . trim($path, '/');
    }

    /**
     * Define the Content-Type charset
     *
     * @param string $prefix
     * @return \Inphinit\Experimental\Rest
     */
    public function path($path)
    {
        if ($path === '') {
            $this->path = '';
        } else {
            $this->path = '/' . trim(str_replace('//', '/', $path), '/');
        }

        return $this;
    }

    /**
     * Define routes
     *
     * @throws \Inphinit\Experimental\Exception
     * @return void
     */
    public function prepare()
    {
        if ($this->ready) {
            return null;
        }

        $this->ready = true;

        $controller = $this->fullController;

        $methods = get_class_methods($controller);
        $allowedMethods = array_keys(self::$valids);

        $classMethods = array_intersect($methods, $allowedMethods);

        if (empty($classMethods)) {
            $level = self::$debuglvl;

            self::$debuglvl = 2;

            throw new Exception($controller . ' controller exists, but is not valid', $level);
        }

        $contentType = $this->contentType . '; charset=' . $this->charset;
        $path = $this->path;

        foreach ($classMethods as $method) {
            if (false === empty(self::$valids[$method])) {
                $route = self::$valids[$method];

                Route::set($route[0], $path . $route[1], function () use ($method, $contentType, $controller) {
                    header('Content-Type: text/html; charset=UTF-8');

                    return call_user_func_array(array(new $controller, $method), func_get_args());
                });
            }
        }
    }
}
