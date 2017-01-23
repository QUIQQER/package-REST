<?php

/**
 * This file contains \QUI\Api\REST
 */

namespace QUI\REST;

use Psr\Http\Message\ServerRequestInterface as RequestInterface;
use Psr\Http\Message\ResponseInterface as ResponseInterface;

use Psr7Middlewares\Middleware\BasePath;

use QUI;
use Slim;
use Monolog;

/**
 * The Rest Server
 *
 * @author www.pcsg.de (Henning Leutz)
 */
class Server
{
    /**
     * @var array
     */
    protected $config = array();

    /**
     * @var Slim\App
     */
    protected $Slim;

    /**
     * Server constructor.
     */
    public function __construct($config = array())
    {
        // slim
        $this->Slim = new Slim\App();
        $container  = $this->Slim->getContainer();

        $container['logger'] = function () {
            $Logger = QUI\Log\Logger::getLogger();

            $Logger->pushHandler(
                new Monolog\Handler\StreamHandler(VAR_DIR . "log/rest.log")
            );

            return $Logger;
        };

        // config
        $this->config = $config;

        if (!is_array($this->config)) {
            $this->config = array();
        }

        if (!isset($this->config['basePath'])) {
            $this->config['basePath'] = '';
        }
    }

    /**
     * Server constructor.
     */
    public function run()
    {
        // Hello World
        $this->Slim->get('/hello/{name}', function (RequestInterface $Request, ResponseInterface $Response, $args) {
            return $Response->write("Hello " . $args['name']);
        });


        // register plugins
        $this->Slim->add(
            (new BasePath($this->config['basePath']))->autodetect(true)
        );

        $this->Slim->run();
    }

    /**
     * Return the Slim App Object
     *
     * @return Slim\App
     */
    public function getSlim()
    {
        return $this->Slim;
    }
}
