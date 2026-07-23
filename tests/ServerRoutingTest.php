<?php

namespace QUI\REST\Tests;

use GuzzleHttp\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use PHPUnit\Framework\TestCase;
use QUI\REST\Response;
use QUI\REST\Server;
use QUI\REST\Tests\Fixtures\RoutingTestProvider;
use QUI\REST\Tests\Fixtures\RoutingTestServer;

class ServerRoutingTest extends TestCase
{
    public function testBaseAndProviderRoutesAreRegisteredOnlyOnce(): void
    {
        $Provider = new RoutingTestProvider();
        $Server = new RoutingTestServer([$Provider]);

        $Server->registerBasePaths();
        $Server->registerBasePaths();
        $Server->registerPackageProviders();
        $Server->registerPackageProviders();

        $patterns = array_map(
            static fn($Route): string => $Route->getPattern(),
            $Server->getSlim()->getRouteCollector()->getRoutes()
        );

        self::assertSame(1, $Provider->getRegisterCalls());
        self::assertSame(1, array_count_values($patterns)['/hello/{name}']);
        self::assertSame(1, array_count_values($patterns)['/docs/{api_name}/{format}']);
        self::assertSame(1, array_count_values($patterns)['/list/{format}']);
        self::assertSame(1, array_count_values($patterns)['/provider-route']);
    }

    public function testEntryPointsContainUniqueBaseAndProviderRoutes(): void
    {
        $Provider = new RoutingTestProvider();
        $Server = new RoutingTestServer([$Provider]);

        $entryPoints = $Server->getEntryPoints();

        self::assertCount(count(array_unique($entryPoints)), $entryPoints);
        self::assertContains('/hello/{name}', $entryPoints);
        self::assertContains('/docs/{api_name}/{format}', $entryPoints);
        self::assertContains('/list/{format}', $entryPoints);
        self::assertContains('/provider-route', $entryPoints);
    }

    public function testHelpListsRoutesAlphabeticallyAndOmitsRootRoute(): void
    {
        $Server = new RoutingTestServer([]);
        $Server->getSlim()->get(
            '/',
            static fn(
                ServerRequestInterface $Request,
                ResponseInterface $Response
            ): ResponseInterface => $Response
        );
        $Server->getSlim()->get(
            '/z-last',
            static fn(
                ServerRequestInterface $Request,
                ResponseInterface $Response
            ): ResponseInterface => $Response
        );
        $Server->getSlim()->get(
            '/a-first',
            static fn(
                ServerRequestInterface $Request,
                ResponseInterface $Response
            ): ResponseInterface => $Response
        );

        $Response = $Server->renderHelp(
            new ServerRequest('GET', '/'),
            new Response()
        );
        $body = (string)$Response->getBody();

        self::assertStringContainsString('Welcome to QUIQQER REST API.', $body);
        self::assertStringNotContainsString(' - /' . "\n", $body);
        self::assertLessThan(
            strpos($body, '/z-last'),
            strpos($body, '/a-first')
        );
    }

    public function testConfiguredInstanceBecomesCurrentInstance(): void
    {
        $Server = Server::getInstance();

        self::assertSame($Server, Server::getCurrentInstance());
        self::assertSame(
            $Server->getBaseHost() . $Server->getBasePath(),
            $Server->getBasePathWithHost()
        );
    }

    public function testDefaultConfigurationProducesRootBasePath(): void
    {
        $Server = new Server();

        self::assertSame('/', $Server->getBasePath());
        self::assertSame('', $Server->getAddress());
    }

    public function testHelloRouteReturnsName(): void
    {
        $Server = new RoutingTestServer([]);
        $Server->registerBasePaths();

        $Response = $Server->getSlim()->handle(
            new ServerRequest('GET', '/api/hello/World')
        );

        self::assertSame(200, $Response->getStatusCode());
        self::assertSame('Hello World', (string)$Response->getBody());
    }
}
