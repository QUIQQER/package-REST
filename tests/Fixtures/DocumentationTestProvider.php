<?php

namespace QUI\REST\Tests\Fixtures;

use QUI;
use QUI\REST\ProviderInterface;
use QUI\REST\Server;

class DocumentationTestProvider implements ProviderInterface
{
    public function __construct(
        private string $name,
        private string $title,
        private bool|string $definitionFile
    ) {
    }

    public function register(Server $Server): void
    {
    }

    public function getOpenApiDefinitionFile(): bool|string
    {
        return $this->definitionFile;
    }

    public function getTitle(?QUI\Locale $Locale = null): string
    {
        return $this->title;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
