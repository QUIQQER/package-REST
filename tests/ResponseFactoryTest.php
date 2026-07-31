<?php

namespace QUI\REST\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use QUI\REST\Response;
use QUI\REST\ResponseFactory;

class ResponseFactoryTest extends TestCase
{
    public function testFactoryImplementsPsr17ResponseFactory(): void
    {
        self::assertInstanceOf(
            ResponseFactoryInterface::class,
            new ResponseFactory()
        );
    }

    public function testFactoryCreatesLegacyCompatibleResponse(): void
    {
        $Response = (new ResponseFactory())->createResponse(
            202,
            'Accepted for processing'
        );

        self::assertInstanceOf(Response::class, $Response);
        self::assertSame(202, $Response->getStatusCode());
        self::assertSame('Accepted for processing', $Response->getReasonPhrase());
        self::assertSame('1.1', $Response->getProtocolVersion());
        self::assertSame([], $Response->getHeaders());
        self::assertSame('', (string)$Response->getBody());
        self::assertSame($Response, $Response->write('response body'));
        self::assertSame('response body', (string)$Response->getBody());
    }
}
