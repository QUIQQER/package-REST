<?php

namespace QUI\REST\Tests;

use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use QUI\REST\Utils\RequestUtils;

class RequestUtilsTest extends TestCase
{
    public function testFalsyQueryValuesArePreserved(): void
    {
        foreach ([0, '0', false, [], ''] as $value) {
            $Request = (new ServerRequest('GET', '/'))
                ->withQueryParams(['field' => $value]);

            self::assertSame(
                $value,
                RequestUtils::getFieldFromRequest($Request, 'field')
            );
        }
    }

    public function testQueryValueTakesPrecedenceEvenWhenEmpty(): void
    {
        $Request = (new ServerRequest('POST', '/'))
            ->withQueryParams(['field' => ''])
            ->withParsedBody(['field' => 'parsed body']);

        self::assertSame(
            '',
            RequestUtils::getFieldFromRequest($Request, 'field')
        );
    }

    public function testFalsyParsedBodyValuesArePreserved(): void
    {
        $Request = (new ServerRequest('POST', '/'))
            ->withParsedBody(['field' => false]);

        self::assertFalse(
            RequestUtils::getFieldFromRequest($Request, 'field')
        );

        $Request = (new ServerRequest('POST', '/'))
            ->withParsedBody(['field' => []]);

        self::assertSame(
            [],
            RequestUtils::getFieldFromRequest($Request, 'field')
        );
    }

    public function testParsedBodyObjectIsSupported(): void
    {
        $Request = (new ServerRequest('POST', '/'))
            ->withParsedBody((object)['field' => 0]);

        self::assertSame(
            0,
            RequestUtils::getFieldFromRequest($Request, 'field')
        );
    }

    public function testFalsyJsonValuesArePreserved(): void
    {
        $Request = new ServerRequest('POST', '/', [], '{"field":0}');

        self::assertSame(
            0,
            RequestUtils::getFieldFromRequest($Request, 'field')
        );

        $Request = new ServerRequest('POST', '/', [], '{"field":false}');

        self::assertFalse(
            RequestUtils::getFieldFromRequest($Request, 'field')
        );

        $Request = new ServerRequest('POST', '/', [], '{"field":[]}');

        self::assertSame(
            [],
            RequestUtils::getFieldFromRequest($Request, 'field')
        );
    }

    public function testMissingFieldReturnsNull(): void
    {
        $Request = new ServerRequest('POST', '/', [], '{"anotherField":"value"}');

        self::assertNull(
            RequestUtils::getFieldFromRequest($Request, 'field')
        );
    }
}
