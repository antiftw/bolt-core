<?php

declare(strict_types=1);

namespace Bolt\Configuration\Parser;

use Tightenco\Collect\Support\Collection;

class ThemeParser extends BaseParser
{
    public function __construct(string $projectDir, private readonly string $path, string $filename = 'theme.yaml')
    {
        parent::__construct($projectDir, $filename);
    }

    /**
     * Read and parse the theme.yml configuration file.
     */
    public function parse(): Collection
    {
        $theme = $this->parseConfigYaml($this->path . '/theme.yaml', true);

        if (! isset($theme['template_directory'])) {
            // the theme itself
            $theme['template_directory'] = './';
        }

        return new Collection($theme);
    }
}
