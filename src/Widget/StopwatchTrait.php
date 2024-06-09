<?php

declare(strict_types=1);

namespace Bolt\Widget;

use Symfony\Component\Stopwatch\Stopwatch;

trait StopwatchTrait
{
    private Stopwatch $stopwatch;

    public function startStopwatch(Stopwatch $stopwatch): void
    {
        $this->stopwatch = $stopwatch;

        $this->stopwatch->start('widget.' . $this->getSlug());
    }

    public function stopStopwatch(): void
    {
        $this->stopwatch->stop('widget.' . $this->getSlug());
    }
}
