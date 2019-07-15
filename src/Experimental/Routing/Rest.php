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

class Rest extends Router
{
    private $contentType = 'application/json';
    private $charset = 'UTF-8';
    private $controller;
    private $fullController;
    private $path;
    private $ready = false;
    private static $valids = array(
        'index'   => array( 'GET',  '/' ),
        'store'   => array( 'POST', '/' ),
        'show'    => array( 'GET',  '/{:[^/]+:}' ),
        'update'  => array( array('PUT', 'PATCH'), '/{:[^/]+:}' ),
        'destroy' => array( 'DELETE', '/{:[^/]+:}' ),
    );

    /**
     * Create REST routes based in a \Controller
     *
     * @param string $controller
     * @return void
     */
    public static function create($controller, $path = null)
    {
        $rest = new static($controller, $path);
        $rest->prepare();
        $rest = null;
    }

    /**
     * Create REST routes based in a \Controller
     *
     * @param string $controller
     * @throws \Inphinit\Experimental\Exception
     * @return void
     */
    public function __construct($controller, $path = null)
    {
        $fullController = parent::$prefixNS . strtr($controller, '.', '\\');
        $fullController = '\\Controller\\' . $fullController;

        if (class_exists($fullController) === false) {
            throw new Exception('Invalid class ' . $fullController, 2);
        }

        $this->controller = $controller;
        $this->fullController = $fullController;

        $this->path = $path !== null ? $path : strtolower('/' . parent::$prefixNS . strtr($controller, '.', '/'));
    }

    /**
     * Define the Content-Type header
     *
     * @param string $contentType
     * @return void
     */
    public function type($contentType)
    {
        $this->contentType = $contentType;

        return $this;
    }

    /**
     * Define the charset of Content-Type header
     *
     * @param string $charset
     * @return void
     */
    public function charset($charset)
    {
        $this->charset = $charset;

        return $this;
    }

    /**
     * Add route to execute
     *
     * @param string|array $method
     * @param string $route
     * @param string $function
     * @return void
     */
    public function extend($method, $route, $function)
    {
        if ($this->ready) {
            throw new Exception('REST instance already executed', 2);
        }

        if (isset(self::$valids[$function])) {
            throw new Exception('Function in use', 2);
        } else {
            self::$valids[$function] = array($method, $route);
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
            throw new Exception($controller . ' controller exists, but is not a valid', 2);
        }

        $contentType = $this->contentType . '; charset=' . $this->charset;

        foreach ($classMethods as $method) {
            $route = empty(self::$valids[$method]) ? false : self::$valids[$method];

            if ($route) {
                Route::set($route[0], $this->path.$route[1], function () use ($method, $contentType, $controller) {
                    header('Content-Type: ' . $contentType);

                    $response = call_user_func_array(array(new $controller, $method), func_get_args());

                    if (is_array($response) || is_object($response)) {
                        $doc = new Document;

                        $doc->fromArray(array(
                            'root' => $response
                        ));

                        if (strcasecmp($contentType, 'application/json')) {
                            $response = $doc->toJson();
                        } elseif (strcasecmp($contentType, 'text/xml') || strcasecmp($contentType, 'application/xml')) {
                            $response = $doc->toString();
                        }
                    }

                    return $response;
                });
            }
        }
    }
}
