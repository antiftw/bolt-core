<?php

declare(strict_types=1);

namespace Bolt\Configuration;

readonly class FileLocation
{
    public function __construct(
        private string $key,
        private string $name,
        private string $basePath,
        private bool   $showAll,
        private string $icon
    ) {}

    public function getKey(): string
    {
        return $this->key;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    public function isShowAll(): bool
    {
        return $this->showAll;
    }
}
