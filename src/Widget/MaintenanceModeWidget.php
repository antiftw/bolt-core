<?php

declare(strict_types=1);

namespace Bolt\Widget;

use Bolt\Widget\Injector\RequestZone;
use Bolt\Widget\Injector\Target;

class MaintenanceModeWidget extends BaseWidget implements TwigAwareInterface
{
    protected ?string $name = 'Maintenance Mode';
    protected string $target = Target::START_OF_BODY;
    protected ?string $zone = RequestZone::FRONTEND;
    protected ?int $priority = 300;

    protected function run(array $params = []): ?string
    {
        return $this->getTwig()->render('@bolt/widget/maintenance_mode.twig');
    }
}
