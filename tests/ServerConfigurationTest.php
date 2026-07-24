<?php

namespace QUI\REST\Tests;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\REST\Server;

class ServerConfigurationTest extends TestCase
{
    public function testMissingBaseHostUsesFallbackWithoutChangingLegacyAddress(): void
    {
        $Server = new Server(['basePath' => '/custom-api/']);
        $expectedHost = rtrim(QUI::conf('globals', 'host'), '/');

        self::assertSame('/custom-api/', $Server->getAddress());
        self::assertSame($expectedHost, $Server->getBaseHost());
        self::assertSame(
            $expectedHost . '/custom-api/',
            $Server->getBasePathWithHost()
        );
    }

    public function testConfiguredAddressIsNotNormalized(): void
    {
        $Server = new Server([
            'basePath' => 'custom-api',
            'baseHost' => 'https://api.example.com/'
        ]);

        self::assertSame(
            'https://api.example.com/custom-api',
            $Server->getAddress()
        );
    }

    public function testBasePathAlwaysHasLeadingAndTrailingSlash(): void
    {
        $Server = new Server([
            'basePath' => 'custom-api',
            'baseHost' => 'https://api.example.com/'
        ]);

        self::assertSame('/custom-api/', $Server->getBasePath());
        self::assertSame(
            'https://api.example.com/custom-api/',
            $Server->getBasePathWithHost()
        );
    }
}
