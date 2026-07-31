<?php

namespace QUI\REST\Tests;

use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use QUI\REST\Response;
use QUI\REST\Tests\Fixtures\DocumentationTestProvider;
use QUI\REST\Tests\Fixtures\RoutingTestServer;
use RuntimeException;

class ServerDocumentationTest extends TestCase
{
    /**
     * @var string[]
     */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }

        $this->temporaryFiles = [];
    }

    public function testDocumentationListReturnsProviderLinksAsJson(): void
    {
        $definitionFile = $this->createDefinitionFile([
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'Documented API',
                'version' => '1.0.0'
            ],
            'paths' => []
        ]);
        $Server = new RoutingTestServer([
            new DocumentationTestProvider(
                'Documented',
                'Documented API',
                $definitionFile
            ),
            new DocumentationTestProvider(
                'Undocumented',
                'Undocumented API',
                false
            )
        ]);

        $Response = $Server->onGetDocsList(
            new ServerRequest('GET', '/api/list/unsupported'),
            new Response(),
            ['format' => 'unsupported']
        );
        $entries = json_decode(
            (string)$Response->getBody(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        self::assertSame('application/json', $Response->getHeaderLine('Content-Type'));
        self::assertSame('Documented API', $entries[0]['title']);
        self::assertSame(
            'https://example.test/api/docs/Documented/html',
            $entries[0]['docsHtml']
        );
        self::assertSame(
            'https://example.test/api/docs/Documented/json',
            $entries[0]['docsJson']
        );
        self::assertFalse($entries[1]['docsHtml']);
        self::assertFalse($entries[1]['docsJson']);
    }

    public function testUnknownDocumentationProviderReturnsMessage(): void
    {
        $Server = new RoutingTestServer([]);

        $Response = $Server->onGetDocsApi(
            new ServerRequest('GET', '/api/docs/Unknown/json'),
            new Response(),
            [
                'api_name' => 'Unknown',
                'format' => 'json'
            ]
        );

        self::assertSame(
            'No OpenApi docs available for API "Unknown".',
            (string)$Response->getBody()
        );
    }

    public function testDocumentationListCanBeRenderedAsHtml(): void
    {
        $Server = new RoutingTestServer([
            new DocumentationTestProvider(
                'Documented',
                'Documented API',
                __FILE__
            )
        ]);

        $Response = $Server->onGetDocsList(
            new ServerRequest('GET', '/api/list/html'),
            new Response(),
            ['format' => 'html']
        );

        self::assertSame('text/html', $Response->getHeaderLine('Content-Type'));
        self::assertStringContainsString('Documented API', (string)$Response->getBody());
        self::assertStringContainsString(
            'https://example.test/api/docs/Documented/html',
            (string)$Response->getBody()
        );
    }

    public function testProviderWithoutDefinitionReturnsMessage(): void
    {
        $Server = new RoutingTestServer([
            new DocumentationTestProvider(
                'Undocumented',
                'Undocumented API',
                false
            )
        ]);

        $Response = $Server->onGetDocsApi(
            new ServerRequest('GET', '/api/docs/Undocumented/json'),
            new Response(),
            [
                'api_name' => 'Undocumented',
                'format' => 'json'
            ]
        );

        self::assertSame(
            'No OpenApi docs available for API "Undocumented".',
            (string)$Response->getBody()
        );
    }

    public function testSupportedOpenApiDefinitionIsExtendedAndReturnedAsJson(): void
    {
        $definitionFile = $this->createDefinitionFile([
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'Documented API',
                'version' => '1.0.0'
            ],
            'paths' => [
                '/items' => [
                    'get' => [
                        'responses' => []
                    ],
                    'post' => [
                        'parameters' => [
                            [
                                'in' => 'query',
                                'name' => 'source',
                                'schema' => [
                                    'type' => 'string'
                                ]
                            ]
                        ],
                        'responses' => []
                    ]
                ]
            ]
        ]);
        $Server = new RoutingTestServer([
            new DocumentationTestProvider(
                'Documented',
                'Documented API',
                $definitionFile
            )
        ]);

        $Response = $Server->onGetDocsApi(
            new ServerRequest('GET', '/api/docs/Documented/unsupported'),
            new Response(),
            [
                'api_name' => 'Documented',
                'format' => 'unsupported'
            ]
        );
        $specification = json_decode(
            (string)$Response->getBody(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        self::assertSame('application/json', $Response->getHeaderLine('Content-Type'));
        self::assertSame(
            [['url' => '/api/']],
            $specification['servers']
        );
        self::assertContains(
            [
                'in' => 'header',
                'name' => 'Accept-Language',
                'description' => 'Language to use for the response in RFC 5646 format. QUIQQER may ignore subtags.',
                'schema' => [
                    'type' => 'string',
                    'example' => 'de-DE'
                ]
            ],
            $specification['paths']['/items']['get']['parameters']
        );
        self::assertCount(
            2,
            $specification['paths']['/items']['post']['parameters']
        );
    }

    public function testSupportedOpenApiDefinitionCanBeRenderedAsHtml(): void
    {
        $definitionFile = $this->createDefinitionFile([
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'Documented API',
                'version' => '1.0.0'
            ],
            'paths' => []
        ]);
        $Server = new RoutingTestServer([
            new DocumentationTestProvider(
                'Documented',
                'Documented API',
                $definitionFile
            )
        ]);

        $Response = $Server->onGetDocsApi(
            new ServerRequest('GET', '/api/docs/Documented/html'),
            new Response(),
            [
                'api_name' => 'Documented',
                'format' => 'html'
            ]
        );

        self::assertSame('text/html', $Response->getHeaderLine('Content-Type'));
        self::assertStringContainsString(
            '<title>Documented API</title>',
            (string)$Response->getBody()
        );
        self::assertStringContainsString(
            'url: "/api/docs/Documented/json"',
            (string)$Response->getBody()
        );
        self::assertStringContainsString(
            'oauth2RedirectUrl: window.location.origin + "',
            (string)$Response->getBody()
        );
        self::assertStringNotContainsString(
            'https://example.test',
            (string)$Response->getBody()
        );
    }

    public function testInvalidOpenApiJsonReturnsInternalServerError(): void
    {
        $definitionFile = $this->createRawDefinitionFile('{invalid JSON');
        $Server = new RoutingTestServer([
            new DocumentationTestProvider(
                'Invalid',
                'Invalid API',
                $definitionFile
            )
        ]);
        $Server->registerBasePaths();

        $Response = $Server->getSlim()->handle(
            new ServerRequest('GET', '/api/docs/Invalid/json')
        );

        self::assertSame(500, $Response->getStatusCode());
        self::assertSame('', (string)$Response->getBody());
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function createDefinitionFile(array $definition): string
    {
        return $this->createRawDefinitionFile(
            json_encode($definition, JSON_THROW_ON_ERROR)
        );
    }

    private function createRawDefinitionFile(string $contents): string
    {
        $file = tempnam(sys_get_temp_dir(), 'quiqqer-rest-openapi-');

        if ($file === false) {
            throw new RuntimeException('Could not create temporary OpenAPI definition file.');
        }

        file_put_contents($file, $contents);
        $this->temporaryFiles[] = $file;

        return $file;
    }
}
