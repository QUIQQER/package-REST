<?php

namespace QUI\REST;

use Exception;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ServerRequestInterface as RequestInterface;
use Psr\Log\LoggerInterface;
use QUI;
use Slim;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Throwable;

use function array_is_list;
use function file_exists;
use function file_get_contents;
use function in_array;
use function is_array;
use function is_readable;
use function json_decode;
use function json_encode;
use function pathinfo;
use function rawurlencode;
use function rtrim;
use function strtolower;
use function trim;

use const ARRAY_FILTER_USE_KEY;
use const PATHINFO_EXTENSION;

/**
 * The Rest Server
 *
 * @author www.pcsg.de (Henning Leutz)
 */
class Server
{
    /**
     * @var array<string, mixed>
     */
    protected array $config = [];

    /**
     * @var Slim\App<null>
     */
    protected $Slim;

    protected Slim\Middleware\ErrorMiddleware $SlimErrorMiddleware;

    /**
     * @var bool
     */
    protected bool $basePathsRegistered = false;

    /**
     * @var bool
     */
    protected bool $packageProdiversRegistered = false;

    /**
     * Last instance of this class initialed by getInstance()
     *
     * @var ?Server
     */
    protected static ?Server $currentInstance = null;

    /**
     * Return a server instance with the quiqqer system configuration
     *
     * @return Server
     * @throws QUI\Exception
     */
    public static function getInstance(): Server
    {
        $Config = Settings::getConfig();

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
     * @return Server
     * @throws QUI\Exception
     */
    public static function getCurrentInstance(): Server
    {
        if (!is_null(self::$currentInstance)) {
            return self::$currentInstance;
        }

        return self::getInstance();
    }

    /**
     * Server constructor.
     *
     * @param array<string, mixed> $config - optional
     */
    public function __construct(array $config = [])
    {
        // config
        $this->config = $config;

        if (!isset($this->config['basePath'])) {
            $this->config['basePath'] = '';
        }

        if (!isset($this->config['baseHost'])) {
            $this->config['baseHost'] = '';
        }

        // slim
        $this->Slim = new Slim\App(
            new ResponseFactory()
        );

        $basePath = '/' . trim($this->config['basePath'], '/');
        $this->Slim->setBasePath($basePath);

        // Define Custom Error Handler
        $customErrorHandler = function (
            ServerRequestInterface $Request,
            Throwable $Exception,
            bool $displayErrorDetails,
            bool $logErrors,
            bool $logErrorDetails,
            ?LoggerInterface $logger = null
        ) {
            if ($Exception instanceof Exception) {
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
                    . "\n\n"
                    . $Exception->getTraceAsString(),
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

                $code = $Exception->getCode();

                if ($code < 100 || $code > 599) {
                    $code = 500;
                }

                $responseBody = json_encode($result);
                $Response = $this->Slim->getResponseFactory()->createResponse($code);
                $Response->getBody()->write($responseBody === false ? '{}' : $responseBody);

                return $Response->withHeader('Content-Type', 'application/json');
            }

            $code = $Exception->getCode();

            if ($code < 100 || $code > 599) {
                $code = 500;
            }

            return $this->Slim->getResponseFactory()->createResponse($code);
        };

        $this->SlimErrorMiddleware = $this->Slim->addErrorMiddleware(
            true,
            true,
            true
        );
        $this->SlimErrorMiddleware->setDefaultErrorHandler(
            $customErrorHandler
        );

        $this->Slim->addBodyParsingMiddleware();
    }

    /**
     * Return the REST API Address
     *
     * @return string
     */
    public function getAddress(): string
    {
        return $this->config['baseHost'] . $this->config['basePath'];
    }

    /**
     * Server constructor.
     *
     * @throws Exception
     */
    public function run(): void
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
            return $Response->write("Hello " . $args['name']);
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
     * @param array<string, mixed> $args
     *
     * @return ResponseInterface
     * @throws QUI\Exception
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
            $entry = [
                'title' => $Provider->getTitle(),
                'docsHtml' => false,
                'docsJson' => false
            ];

            $baseUrlDocs = $this->getBasePathWithHost() . 'docs/' . $Provider->getName() . '/';

            if (!empty($specificationFile)) {
                $entry['docsHtml'] = $baseUrlDocs . 'html';
                $entry['docsJson'] = $baseUrlDocs . 'json';
            }

            $entries[] = $entry;
        }

        if ($format === 'json') {
            return $Response
                ->write(json_encode($entries))
                ->withHeader('Content-Type', 'application/json');
        }

        $Engine = QUI::getTemplateManager()->getEngine();

        $Engine->assign([
            'entries' => $entries
        ]);

        $Package = QUI::getPackage('quiqqer/rest');
        $tplDir = $Package->getDir() . 'bin/template/';
        $html = $Engine->fetch($tplDir . 'DocsList.html');

        return $Response
            ->write($html)
            ->withHeader('Content-Type', 'text/html');
    }

    /**
     * @param RequestInterface $Request
     * @param ResponseInterface $Response
     * @param array<string, mixed> $args
     *
     * @return ResponseInterface
     * @throws QUI\Exception
     * @throws \JsonException
     * @throws ParseException
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

        $apiName = $args['api_name'];
        $providers = $this->getProvidersFromPackages();

        /** @var Response $Response */
        if (empty($providers[$apiName])) {
            return $Response->write("No OpenApi docs available for API \"" . $apiName . "\".");
        }

        $Provider = $providers[$apiName];
        $openApiDefinitionFile = $Provider->getOpenApiDefinitionFile();

        if (
            !$openApiDefinitionFile ||
            !file_exists($openApiDefinitionFile) ||
            !is_readable($openApiDefinitionFile)
        ) {
            return $Response->write("No OpenApi docs available for API \"" . $apiName . "\".");
        }

        $specificationArray = $this->loadOpenApiDefinition($openApiDefinitionFile);

        // Add servers
        $specificationArray['servers'] = [
            [
                'url' => $this->getBasePath()
            ]
        ];

        // Add "Accept-Language" request-header-parameter to all paths and their methods
        if (isset($specificationArray['paths'])) {
            $acceptLanguageHeaderParameterSchema = [
                'in' => 'header',
                'name' => 'Accept-Language',
                'description' => 'Language to use for the response in RFC 5646 format. QUIQQER may ignore subtags.',
                'schema' => [
                    'type' => 'string',
                    'example' => 'de-DE'
                ]
            ];

            foreach ($specificationArray['paths'] as &$methods) {
                foreach ($methods as &$method) {
                    $method['parameters'][] = $acceptLanguageHeaderParameterSchema;
                }
            }
        }

        try {
            QUI::getEvents()->fireEvent(
                'quiqqerRestLoadOpenApiSpecification',
                [
                    $apiName,
                    &$specificationArray
                ]
            );
        } catch (Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }

        $specificationJson = json_encode(
            $specificationArray,
            JSON_THROW_ON_ERROR
        );

        if ($format === 'json') {
            return $Response
                ->write($specificationJson)
                ->withHeader('Content-Type', 'application/json');
        }

        // HTML Output
        try {
            $Engine = QUI::getTemplateManager()->getEngine();
            $Package = QUI::getPackage('quiqqer/rest');
        } catch (Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            $Response->write('Something went wrong. Please contact an adminstrator.');
            return $Response->withStatus(500);
        }

        $tplDir = $Package->getDir() . 'bin/template/';

        $Engine->assign([
            'openApiSpecificationFile' => $this->getBasePath()
                . 'docs/'
                . rawurlencode($apiName)
                . '/json',
            'URL_OPT_DIR' => URL_OPT_DIR,
            'apiTitle' => !empty($specificationArray['info']['title']) ?
                $specificationArray['info']['title'] :
                'REST API Documentation'
        ]);

        $html = $Engine->fetch($tplDir . 'index.html');

        return $Response
            ->write($html)
            ->withHeader('Content-Type', 'text/html');
    }

    /**
     * @return array<string, mixed>
     * @throws \JsonException
     * @throws QUI\Exception
     * @throws ParseException
     */
    private function loadOpenApiDefinition(string $definitionFile): array
    {
        $contents = file_get_contents($definitionFile);

        if ($contents === false) {
            throw new QUI\Exception(
                'Could not read OpenAPI definition file.',
                500
            );
        }

        $extension = strtolower(pathinfo($definitionFile, PATHINFO_EXTENSION));

        if (in_array($extension, ['yaml', 'yml'], true)) {
            $specification = Yaml::parse($contents);
        } else {
            $specification = json_decode(
                $contents,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        }

        if (!is_array($specification) || array_is_list($specification)) {
            throw new QUI\Exception(
                'OpenAPI definition must contain an object.',
                500
            );
        }

        /** @var array<string, mixed> $specification */
        return $specification;
    }

    /**
     * Register all REST-Providers fro package.xml files
     *
     * @return void
     */
    public function registerPackageProviders(): void
    {
        if ($this->packageProdiversRegistered) {
            return;
        }

        // packages provider
        $provider = $this->getProvidersFromPackages();

        foreach ($provider as $Provider) {
            $Provider->register($this);
        }

        $this->packageProdiversRegistered = true;
    }

    /**
     * Get all entry points (routes) of all registered REST providers
     *
     * @return list<string>
     */
    public function getEntryPoints(): array
    {
        $this->registerBasePaths();
        $this->registerPackageProviders();

        $routes = $this->getSlim()->getRouteCollector()->getRoutes();
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
     * @return Slim\App<null>
     */
    public function getSlim(): Slim\App
    {
        return $this->Slim;
    }

    /**
     * Return the Slim error middleware.
     */
    public function getSlimErrorMiddleware(): Slim\Middleware\ErrorMiddleware
    {
        return $this->SlimErrorMiddleware;
    }

    /**
     * Return REST API base path (relative to URI)
     *
     * @return string
     */
    public function getBasePath(): string
    {
        return rtrim('/' . trim($this->config['basePath'], '/'), '/') . '/';
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

        return rtrim($baseUrl, '/');
    }

    /**
     * @return string - Base path with host WITH trailing slash (/)
     */
    public function getBasePathWithHost(): string
    {
        return $this->getBaseHost() . $this->getBasePath();
    }

    /**
     * Return all provider from the packages
     *
     * @return ProviderInterface[]
     */
    protected function getProvidersFromPackages(): array
    {
        $packages = QUI::getPackageManager()->getInstalled();
        $result = [];

        try {
            $providerList = QUI\Cache\Manager::get('quiqqer/rest/providerList');
        } catch (QUI\Cache\Exception) {
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
                }, ARRAY_FILTER_USE_KEY);

                if (isset($provider['rest'])) {
                    $providerList = array_merge($providerList, $provider['rest']);
                }
            }

            try {
                QUI\Cache\Manager::set(
                    'quiqqer/rest/providerList',
                    $providerList
                );
            } catch (Exception $Exception) {
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
            } catch (Exception $Exception) {
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
     * @param array<string, mixed> $args
     * @return mixed
     */
    protected function help(
        RequestInterface $Request,
        ResponseInterface $Response,
        array $args
    ): mixed {
        $patterns = [];
        $routes = $this->getSlim()->getRouteCollector()->getRoutes();

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
            $output .= ' - ' . $pattern . "\n\n";
        }

        $output .= '</pre>';

        return $Response->write($output);
    }
}
