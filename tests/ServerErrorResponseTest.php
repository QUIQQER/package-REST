<?php

namespace QUI\REST\Tests;

use Error;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\REST\Server;
use RuntimeException;

class ServerErrorResponseTest extends TestCase
{
    public function testQuiExceptionIsReturnedAsJsonResponse(): void
    {
        $Server = new Server(['basePath' => '/api']);
        $Server->getSlim()->get('/failing-route', static function (): void {
            throw new QUI\Exception('Expected test exception', 422);
        });

        $Response = $Server->getSlim()->handle(
            new ServerRequest('GET', '/api/failing-route')
        );

        self::assertSame(422, $Response->getStatusCode());
        self::assertSame('application/json', $Response->getHeaderLine('Content-Type'));
        self::assertSame(
            [
                'error' => [
                    'code' => 422,
                    'message' => 'Expected test exception',
                    'type' => QUI\Exception::class,
                    'context' => []
                ]
            ],
            json_decode((string)$Response->getBody(), true)
        );
    }

    public function testInvalidQuiExceptionCodeIsNormalizedToInternalServerError(): void
    {
        $Server = new Server(['basePath' => '/api']);
        $Server->getSlim()->get('/failing-route', static function (): void {
            throw new QUI\Exception('Expected test exception');
        });

        $Response = $Server->getSlim()->handle(
            new ServerRequest('GET', '/api/failing-route')
        );

        self::assertSame(500, $Response->getStatusCode());
        self::assertSame('application/json', $Response->getHeaderLine('Content-Type'));
        self::assertJson((string)$Response->getBody());
    }

    public function testGenericExceptionUsesItsHttpStatusCode(): void
    {
        $Server = new Server(['basePath' => '/api']);
        $Server->getSlim()->get('/failing-route', static function (): void {
            throw new Error('Expected test error', 503);
        });

        $Response = $Server->getSlim()->handle(
            new ServerRequest('GET', '/api/failing-route')
        );

        self::assertSame(503, $Response->getStatusCode());
        self::assertSame('', (string)$Response->getBody());
    }

    public function testInvalidGenericExceptionCodeIsNormalizedToInternalServerError(): void
    {
        $Server = new Server(['basePath' => '/api']);
        $Server->getSlim()->get('/failing-route', static function (): void {
            throw new RuntimeException('Expected test exception');
        });

        $Response = $Server->getSlim()->handle(
            new ServerRequest('GET', '/api/failing-route')
        );

        self::assertSame(500, $Response->getStatusCode());
        self::assertSame('', (string)$Response->getBody());
    }
}
