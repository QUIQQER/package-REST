<?php

namespace QUI\REST\Tests\Fixtures;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use QUI;
use QUI\REST\ProviderInterface;
use QUI\REST\Server;

class RoutingTestProvider implements ProviderInterface
{
    private int $registerCalls = 0;

    public function register(Server $Server): void
    {
        $this->registerCalls++;
        $Server->getSlim()->get(
            '/provider-route',
            static fn(
                ServerRequestInterface $Request,
                ResponseInterface $Response
            ): ResponseInterface => $Response
        );
    }

    public function getOpenApiDefinitionFile(): bool|string
    {
        return false;
    }

    public function getTitle(?QUI\Locale $Locale = null): string
    {
        return 'Routing Test Provider';
    }

    public function getName(): string
    {
        return 'RoutingTest';
    }

    public function getRegisterCalls(): int
    {
        return $this->registerCalls;
    }
}
