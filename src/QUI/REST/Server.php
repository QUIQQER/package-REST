<?php

/**
 * This file contains \QUI\REST\Server
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
     *
     * @param array $config - optional
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

        $container['errorHandler'] = function ($container) {
            return function (
                RequestInterface $Request,
                ResponseInterface $Response,
                $Exception
            ) use ($container) {

                if ($Exception instanceof QUI\Exception) {
                    $result = array(
                        'error' => $Exception->toArray()
                    );

                    QUI\System\Log::writeException(
                        $Exception,
                        QUI\System\Log::LEVEL_ERROR,
                        array(
                            'package' => 'quiqqer/rest'
                        ),
                        'rest.log'
                    );

                    return $Response->withStatus(500)
                        ->withHeader('Content-Type', 'application/json')
                        ->write(json_encode($result));
                }

                return $Response;
            };
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
        $self = $this;

        $this->Slim->get(
            '/',
            function (RequestInterface $Request, ResponseInterface $Response, $args) use ($self) {
                return $self->help($Request, $Response, $args);
            }
        );

        // Hello World
        $this->Slim->get('/hello/{name}', function (RequestInterface $Request, ResponseInterface $Response, $args) {
            return $Response->write("Hello " . $args['name']);
        });


        // packages provider
        $provider = $this->getProvidersFromPackages();

        /* @var $Provider ProviderInterface */
        foreach ($provider as $Provider) {
            $Provider->register($this);
        }

        // register middlewares
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

    /**
     * Return all provider from the packages
     *
     * @return array
     */
    protected function getProvidersFromPackages()
    {
        $packages = QUI::getPackageManager()->getInstalled();
        $result   = array();

        try {
            $providerList = QUI\Cache\Manager::get('quiqqer/rest/providerList');
        } catch (QUI\Cache\Exception $Exception) {
            $providerList = array();

            /* @var $Package QUI\Package\Package */
            foreach ($packages as $package) {
                $provider = QUI::getPackage($package['name'])->getProvider();
                $provider = array_filter($provider, function ($key) {
                    return $key === 'rest';
                }, \ARRAY_FILTER_USE_KEY);

                if (isset($provider['rest'])) {
                    $providerList = array_merge($providerList, $provider['rest']);
                }
            }

            QUI\Cache\Manager::set(
                'quiqqer/rest/providerList',
                $providerList
            );
        }

        // initialize the instances
        foreach ($providerList as $provider) {
            try {
                $Provider = new $provider();

                if ($Provider instanceof ProviderInterface) {
                    $result[] = $Provider;
                }
            } catch (\Exception $exception) {
                QUI\System\Log::writeException($provider);
            }
        }

        return $result;
    }

    /**
     * Helper message for the request
     *
     * @param RequestInterface $Request
     * @param ResponseInterface $Response
     * @param $args
     * @return mixed
     */
    protected function help(RequestInterface $Request, ResponseInterface $Response, $args)
    {
        $patterns = array();
        $routes   = $this->Slim->getContainer()->get('router')->getRoutes();

        foreach ($routes as $Route) {
            if ($Route->getPattern() != '/') {
                $patterns[] = $Route->getPattern();
            }
        }

        $output = '<pre>
  _______          _________ _______  _______  _______  _______
 (  ___  )|\     /|\__   __/(  ___  )(  ___  )(  ____ \(  ____ )
 | (   ) || )   ( |   ) (   | (   ) || (   ) || (    \/| (    )|
 | |   | || |   | |   | |   | |   | || |   | || (__    | (____)|
 | |   | || |   | |   | |   | |   | || |   | ||  __)   |     __)
 | | /\| || |   | |   | |   | | /\| || | /\| || (      | (\ (
 | (_\ \ || (___) |___) (___| (_\ \ || (_\ \ || (____/\| ) \ \__
 (____\/_)(_______)\_______/(____\/_)(____\/_)(_______/|/   \__/


 Welcome to QUIQQER REST API.


';

        foreach ($patterns as $pattern) {
            $output .= ' - ' . $pattern . "\n\n";
        }

        $output .= '</pre>';

        return $Response->write($output);
    }
}
