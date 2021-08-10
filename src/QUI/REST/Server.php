<?php

namespace QUI\REST;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ServerRequestInterface as RequestInterface;
use Psr\Http\Message\ResponseInterface as ResponseInterface;

use Psr\Log\LoggerInterface;
use QUI;
use Slim;

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

    /**
     * @var bool
     */
    protected $basePathsRegistered = false;

    /**
     * @var bool
     */
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
        // config
        $this->config = $config;

        if (!is_array($this->config)) {
            $this->config = [];
        }

        if (!isset($this->config['basePath'])) {
            $this->config['basePath'] = '';
        }

        // slim
        $this->Slim = new Slim\App(
            new ResponseFactory()
        );

//        $this->Slim = Slim\Factory\AppFactory::create();
        $this->Slim->setBasePath(\rtrim($this->config['basePath'], '/'));

        // Define Custom Error Handler
        $customErrorHandler = function (
            ServerRequestInterface $Request,
            \Throwable $Exception,
            bool $displayErrorDetails,
            bool $logErrors,
            bool $logErrorDetails,
            ?LoggerInterface $logger = null
        ) {
            if ($Exception instanceof \Exception) {
                QUI\System\Log::writeException(
                    $Exception,
                    QUI\System\Log::LEVEL_ERROR,
                    [
                        'package' => 'quiqqer/rest'
                    ],
                    'rest.log'
                );
            } else {
                QUI\System\Log::addError(
                    $Exception->getMessage()
                    ."\n\n"
                    .$Exception->getTraceAsString(),
                    [
                        'package' => 'quiqqer/rest'
                    ],
                    'rest.log'
                );
            }

            if ($Exception instanceof QUI\Exception) {
                $result = [
                    'error' => $Exception->toArray()
                ];

                $Response = $this->Slim->getResponseFactory()->createResponse(
                    $Exception->getCode(),
                    \json_encode($result)
                );

                return $Response->withHeader('Content-Type', 'application/json');
            }

            return $this->Slim->getResponseFactory()->createResponse(500);
        };

        $ErrorMiddleware = $this->Slim->addErrorMiddleware(true, true, true);
        $ErrorMiddleware->setDefaultErrorHandler($customErrorHandler);

        $this->Slim->addBodyParsingMiddleware();
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

        $this->registerBasePaths();
        $this->registerPackageProviders();

        $this->Slim->run();
    }

    /**
     * Register base paths that are alaways available.
     *
     * @return void
     */
    public function registerBasePaths(): void
    {
        if ($this->basePathsRegistered) {
            return;
        }

        // Hello World
        $this->Slim->get('/hello/{name}', function (RequestInterface $Request, ResponseInterface $Response, $args) {
            /** @var Response $Response */
            return $Response->write("Hello ".$args['name']);
        });

        $this->Slim->get(
            '/docs/{api_name}/{format}',
            [$this, 'onGetDocsApi']
        );

        $this->Slim->get(
            '/list/{format}',
            [$this, 'onGetDocsList']
        );

        $this->basePathsRegistered = true;
    }

    /**
     * @param RequestInterface $Request
     * @param ResponseInterface $Response
     * @param array $args
     *
     * @return ResponseInterface
     */
    public function onGetDocsList(
        RequestInterface $Request,
        ResponseInterface $Response,
        array $args
    ): ResponseInterface {
        $format = $args['format'];

        switch ($format) {
            case 'html':
                break;

            default:
                $format = 'json';
        }

        $entries = [];

        foreach ($this->getProvidersFromPackages() as $Provider) {
            $specificationFile = $Provider->getOpenApiDefinitionFile();
            $entry             = [
                'title'    => $Provider->getTitle(),
                'docsHtml' => false,
                'docsJson' => false
            ];

            $baseUrlDocs = $this->getBasePathWithHost().'docs/'.$Provider->getName().'/';

            if (!empty($specificationFile)) {
                $entry['docsHtml'] = $baseUrlDocs.'html';
                $entry['docsJson'] = $baseUrlDocs.'json';
            }

            $entries[] = $entry;
        }

        if ($format === 'json') {
            return $Response
                ->write(\json_encode($entries))
                ->withHeader('Content-Type', 'application/json');
        }

        $Engine = QUI::getTemplateManager()->getEngine();

        $Engine->assign([
            'entries' => $entries
        ]);

        $Package = QUI::getPackage('quiqqer/rest');
        $tplDir  = $Package->getDir().'bin/template/';
        $html    = $Engine->fetch($tplDir.'DocsList.html');

        return $Response
            ->write($html)
            ->withHeader('Content-Type', 'text/html');
    }

    /**
     * @param RequestInterface $Request
     * @param ResponseInterface $Response
     * @param array $args
     *
     * @return ResponseInterface
     */
    public function onGetDocsApi(
        RequestInterface $Request,
        ResponseInterface $Response,
        array $args
    ): ResponseInterface {
        $format = $args['format'];

        switch ($format) {
            case 'html':
                break;

            default:
                $format = 'json';
        }

        $apiName   = $args['api_name'];
        $providers = $this->getProvidersFromPackages();

        /** @var Response $Response */
        if (empty($providers[$apiName])) {
            return $Response->write("No OpenApi docs available for API \"".$apiName."\".");
        }

        $Provider              = $providers[$apiName];
        $openApiDefinitionFile = $Provider->getOpenApiDefinitionFile();

        if (!$openApiDefinitionFile ||
            !\file_exists($openApiDefinitionFile) ||
            !\is_readable($openApiDefinitionFile)) {
            return $Response->write("No OpenApi docs available for API \"".$apiName."\".");
        }

        $specificationArray = \json_decode(\file_get_contents($openApiDefinitionFile), true);

        // Add servers
        $specificationArray['servers'] = [
            [
                'url' => $this->getBasePathWithHost()
            ]
        ];

        try {
            QUI::getEvents()->fireEvent(
                'quiqqerRestLoadOpenApiSpecification',
                [
                    $apiName,
                    &$specificationArray
                ]
            );
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }

        $specificationJson = \json_encode($specificationArray);

        if ($format === 'json') {
            return $Response
                ->write($specificationJson)
                ->withHeader('Content-Type', 'application/json');
        }

        // HTML Output
        try {
            $Engine  = QUI::getTemplateManager()->getEngine();
            $Package = QUI::getPackage('quiqqer/rest');
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            $Response->write('Something went wrong. Please contact an adminstrator.');
            return $Response->withStatus(500);
        }

        $tplDir = $Package->getDir().'bin/template/';
        $varDir = $Package->getVarDir().'bin/';

        QUI\Utils\System\File::mkdir($varDir);

        // Copy file content to bin dir
        $binFile = $varDir.'specification.json';

        \file_put_contents($binFile, $specificationJson);

        $fullOptDir = self::getBaseHost().URL_OPT_DIR;
        $fullVarDir = self::getBaseHost().URL_VAR_DIR;

        $Engine->assign([
            'openApiSpecificationFile' => \str_replace(VAR_DIR, $fullVarDir, $binFile),
            'URL_OPT_DIR'              => $fullOptDir
        ]);

        $html = $Engine->fetch($tplDir.'index.html');

        return $Response
            ->write($html)
            ->withHeader('Content-Type', 'text/html');
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
        $this->registerBasePaths();
        $this->registerPackageProviders();

        $routes      = $this->getSlim()->getRouteCollector()->getRoutes();
        $entryPoints = [];

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
     * Return REST API base path (relative to URI)
     *
     * @return string
     */
    public function getBasePath(): string
    {
        return \rtrim($this->config['basePath'], '/').'/';
    }

    /**
     * @return string - API base host WITHOUT trailing slash (/)
     */
    public function getBaseHost(): string
    {
        $baseUrl = $this->config['baseHost'];

        if (empty($baseUrl)) {
            $baseUrl = QUI::conf('globals', 'host');
        }

        return \rtrim($baseUrl, '/');
    }

    /**
     * @return string - Base path with host WITH trailing slash (/)
     */
    public function getBasePathWithHost(): string
    {
        return $this->getBaseHost().$this->getBasePath();
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
                    $result[$Provider->getName()] = $Provider;
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
