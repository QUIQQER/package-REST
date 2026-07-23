<?php

namespace QUI\REST\Tests;

use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use QUI;
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

    public function testZeroPathArgumentIsPreserved(): void
    {
        $Request = (new ServerRequest('GET', '/resource/0'))
            ->withAttribute('resourceId', '0');

        self::assertSame(
            '0',
            RequestUtils::getArgFromRequest($Request, 'resourceId')
        );
    }

    public function testMissingPathArgumentReturnsFalse(): void
    {
        $Request = new ServerRequest('GET', '/');

        self::assertFalse(
            RequestUtils::getArgFromRequest($Request, 'resourceId')
        );
    }

    public function testJsonDetectionRequiresAnArrayOrObject(): void
    {
        self::assertTrue(RequestUtils::isJson('{"value":1}'));
        self::assertTrue(RequestUtils::isJson('[1,2,3]'));
        self::assertFalse(RequestUtils::isJson('"value"'));
        self::assertFalse(RequestUtils::isJson('invalid JSON'));
    }

    public function testRequestedLanguageUsesFirstLanguageSubtag(): void
    {
        $Request = QUI::getRequest();
        $previousHeader = $Request->headers->get('Accept-Language');

        try {
            $Request->headers->remove('Accept-Language');
            self::assertNull(RequestUtils::getRequestedLanguage());

            $Request->headers->set('Accept-Language', '');
            self::assertNull(RequestUtils::getRequestedLanguage());

            $Request->headers->set('Accept-Language', 'de-DE,de;q=0.9');
            self::assertSame('de', RequestUtils::getRequestedLanguage());
        } finally {
            if ($previousHeader === null) {
                $Request->headers->remove('Accept-Language');
            } else {
                $Request->headers->set('Accept-Language', $previousHeader);
            }
        }
    }
}
