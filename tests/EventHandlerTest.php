<?php

namespace QUI\REST\Tests;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\REST\EventHandler;

class EventHandlerTest extends TestCase
{
    public function testRequestWithDifferentConfiguredHostIsIgnored(): void
    {
        $Config = QUI::getPackage('quiqqer/rest')->getConfig();
        self::assertNotNull($Config);
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
        $Config = QUI::getPackage('quiqqer/rest')->getConfig();
        self::assertNotNull($Config);
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
