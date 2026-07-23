<?php

namespace QUI\REST\Tests;

use PHPUnit\Framework\TestCase;
use QUI\REST\Response;

class ResponseTest extends TestCase
{
    public function testWriteAppendsContentAndReturnsSameResponse(): void
    {
        $Response = new Response();

        self::assertSame($Response, $Response->write('first'));
        self::assertSame($Response, $Response->write(' second'));
        self::assertSame('first second', (string)$Response->getBody());
    }
}
