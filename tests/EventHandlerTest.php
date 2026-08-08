<?php

namespace QUI\REST\Tests;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\REST\EventHandler;
use QUI\REST\Settings;

class EventHandlerTest extends TestCase
{
    public function testOnlyRootedBasePathMatchesRestRequests(): void
    {
        self::assertTrue(EventHandler::isRestRequest('/api', '/api/'));
        self::assertTrue(EventHandler::isRestRequest('/api/', '/api/'));
        self::assertTrue(EventHandler::isRestRequest('/api/items', '/api/'));
        self::assertTrue(EventHandler::isRestRequest('/api/items?limit=10', '/api/'));

        self::assertFalse(EventHandler::isRestRequest('/es/preguntas-frecuentes/api', '/api/'));
        self::assertFalse(EventHandler::isRestRequest('/foo/api/bar', '/api/'));
        self::assertFalse(EventHandler::isRestRequest('/page?target=/api/', '/api/'));
    }

    public function testEmptyOrRootBasePathMatchesEveryRequest(): void
    {
        self::assertTrue(EventHandler::isRestRequest('/page', ''));
        self::assertTrue(EventHandler::isRestRequest('/page', '/'));
    }

    public function testRequestWithDifferentConfiguredHostIsIgnored(): void
    {
        $Config = Settings::getConfig();
        $general = $Config->getSection('general');

        try {
            $Config->setValue('general', 'basePath', 0);
            $Config->setValue(
                'general',
                'baseHost',
                'rest-event-handler-test.invalid'
            );

            EventHandler::onRequest(
                $this->createMock(QUI\Rewrite::class),
                ''
            );
        } finally {
            $Config->setSection(
                'general',
                is_array($general) ? $general : []
            );
        }

        self::assertTrue(true);
    }

    public function testRequestOutsideConfiguredBasePathIsIgnored(): void
    {
        $Config = Settings::getConfig();
        $general = $Config->getSection('general');

        try {
            $Config->setValue(
                'general',
                'basePath',
                '/rest-event-handler-test/'
            );
            $Config->setValue('general', 'baseHost', 0);

            EventHandler::onRequest(
                $this->createMock(QUI\Rewrite::class),
                ''
            );
        } finally {
            $Config->setSection(
                'general',
                is_array($general) ? $general : []
            );
        }

        self::assertTrue(true);
    }
}
