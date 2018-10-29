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
    protected $config = [];

    /**
     * @var Slim\App
     */
    protected $Slim;

    protected $packageProdiversRegistered = false;

    /**
     * Last instance of this class initiaded by getInstance()
     *
     * @var \QUI\Rest\Server
     */
    protected static $currentInstance = null;

    /**
     * Return a server instance with the quiqqer system configuration
     *
     * @return Server
     * @throws QUI\Exception
     */
    public static function getInstance()
    {
        $Package = QUI::getPackage('quiqqer/rest');
        $Config  = $Package->getConfig();

        $basePath = $Config->getValue('general', 'basePath');
        $baseHost = $Config->getValue('general', 'baseHost');

        if (empty($baseHost)) {
            $baseHost = HOST;
        }


        if (empty($basePath)) {
            $basePath = '';
        }

        self::$currentInstance = new self([
            'basePath' => $basePath,
            'baseHost' => $baseHost
        ]);

        return self::$currentInstance;
    }

    /**
     * Returns the last instance that was initiated with getInstance()
     *
     * If no instance has been initiated -> create a new one
     *
     * @return \QUI\REST\Server
     * @throws QUI\Exception
     */
    public static function getCurrentInstance()
    {
        if (!is_null(self::$currentInstance)) {
            return self::$currentInstance;
        }

        return self::getInstance();
    }

    /**
     * Server constructor.
     *
     * @param array $config - optional
     */
    public function __construct($config = [])
    {
        // slim
        $this->Slim = new Slim\App();
        $container  = $this->Slim->getContainer();

        $container['logger'] = function () {
            $Logger = QUI\Log\Logger::getLogger();

            $Logger->pushHandler(
                new Monolog\Handler\StreamHandler(VAR_DIR."log/rest.log")
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
                    $result = [
                        'error' => $Exception->toArray()
                    ];

                    QUI\System\Log::writeException(
                        $Exception,
                        QUI\System\Log::LEVEL_ERROR,
                        [
                            'package' => 'quiqqer/rest'
                        ],
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
            $this->config = [];
        }

        if (!isset($this->config['basePath'])) {
            $this->config['basePath'] = '';
        }
    }

    /**
     * Return the REST API Address
     *
     * @return string
     */
    public function getAddress()
    {
        return $this->config['baseHost'].$this->config['basePath'];
    }

    /**
     * Server constructor.
     *
     * @throws \Exception
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
            return $Response->write("Hello ".$args['name']);
        });

        $this->registerPackageProviders();

        // register middlewares
        $this->Slim->add(
            (new BasePath($this->config['basePath']))->autodetect(true)
        );

        $this->Slim->run();
    }

    /**
     * Register all REST-Providers fro package.xml files
     *
     * @return void
     */
    public function registerPackageProviders()
    {
        if ($this->packageProdiversRegistered) {
            return;
        }

        // packages provider
        $provider = $this->getProvidersFromPackages();

        /* @var $Provider ProviderInterface */
        foreach ($provider as $Provider) {
            $Provider->register($this);
        }

        $this->packageProdiversRegistered = true;
    }

    /**
     * Get all entry points (routes) of all registered REST prodivers
     *
     * @return array
     */
    public function getEntryPoints()
    {
        $this->registerPackageProviders();
        $routes      = $this->getSlim()->getContainer()->get('router')->getRoutes();
        $entryPoints = [];

        /** @var \Slim\Interfaces\RouteInterface $Route */
        foreach ($routes as $Route) {
            $entryPoints[] = $Route->getPattern();
        }

        $entryPoints = array_unique($entryPoints);

        return array_values($entryPoints);
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
     * @return ProviderInterface[]
     */
    protected function getProvidersFromPackages()
    {
        $packages = QUI::getPackageManager()->getInstalled();
        $result   = [];

        try {
            $providerList = QUI\Cache\Manager::get('quiqqer/rest/providerList');
        } catch (QUI\Cache\Exception $Exception) {
            $providerList = [];

            /* @var $Package QUI\Package\Package */
            foreach ($packages as $package) {
                try {
                    $Package = QUI::getPackage($package['name']);
                } catch (QUI\Exception $Exception) {
                    QUI\System\Log::writeException($Exception);
                    continue;
                }

                $provider = $Package->getProvider();
                $provider = array_filter($provider, function ($key) {
                    return $key === 'rest';
                }, \ARRAY_FILTER_USE_KEY);

                if (isset($provider['rest'])) {
                    $providerList = array_merge($providerList, $provider['rest']);
                }
            }

            try {
                QUI\Cache\Manager::set(
                    'quiqqer/rest/providerList',
                    $providerList
                );
            } catch (\Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);
            }
        }

        // initialize the instances
        foreach ($providerList as $provider) {
            if (!class_exists($provider)) {
                continue;
            }

            try {
                $Provider = new $provider();

                if ($Provider instanceof ProviderInterface) {
                    $result[] = $Provider;
                }
            } catch (\Exception $Exception) {
                QUI\System\Log::writeException($Exception);
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
        $patterns = [];
        $routes   = $this->Slim->getContainer()->get('router')->getRoutes();

        foreach ($routes as $Route) {
            if ($Route->getPattern() != '/') {
                $patterns[] = $Route->getPattern();
            }
        }

        sort($patterns);

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
            $output .= ' - '.$pattern."\n\n";
        }

        $output .= '</pre>';

        return $Response->write($output);
    }
}
