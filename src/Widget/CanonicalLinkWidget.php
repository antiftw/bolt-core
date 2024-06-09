<?php

declare(strict_types=1);

namespace Bolt\Widget;

use Bolt\Canonical;
use Bolt\Configuration\Config;
use Bolt\Widget\Injector\RequestZone;
use Bolt\Widget\Injector\Target;
use Twig\Environment;

class CanonicalLinkWidget extends BaseWidget
{
    protected ?string $name = 'Canonical Link';
    protected string $target = Target::END_OF_HEAD;
    protected ?string $zone = RequestZone::FRONTEND;
    protected ?int $priority = 200;

    private string $defaultTemplate = '@bolt/widget/canonical.html.twig';

    public function __construct(
        private readonly Canonical $canonical,
        private readonly Config $config,
    ) {}

    protected function run(array $params = []): ?string
    {
        $template = $this->config->get('general/canonical_template', $this->defaultTemplate);
        $absolute = ! $this->config->get('general/relative_canonical_url', false);

        return $this->getTwig()->render(
            $template,
            [
                'canonical' => $this->canonical->get(null, [], $absolute),
            ]
        );
    }
}
