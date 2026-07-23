<?php

namespace QUI\REST\Tests\Fixtures;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use QUI\REST\ProviderInterface;
use QUI\REST\Server;

class RoutingTestServer extends Server
{
    /**
     * @param ProviderInterface[] $Providers
     */
    public function __construct(private array $Providers)
    {
        parent::__construct([
            'basePath' => '/api',
            'baseHost' => 'https://example.test'
        ]);
    }

    /**
     * @return ProviderInterface[]
     */
    protected function getProvidersFromPackages(): array
    {
        $result = [];

        foreach ($this->Providers as $Provider) {
            $result[$Provider->getName()] = $Provider;
        }

        return $result;
    }

    public function renderHelp(
        ServerRequestInterface $Request,
        ResponseInterface $Response
    ): ResponseInterface {
        return $this->help($Request, $Response, []);
    }
}
