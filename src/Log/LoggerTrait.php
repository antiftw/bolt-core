<?php

declare(strict_types=1);

namespace Bolt\Log;

use ECSPrefix202206\Symfony\Contracts\Service\Attribute\Required;
use Psr\Log\LoggerInterface;

trait LoggerTrait
{
    protected LoggerInterface $logger;

    #[Required]
    public function setLogger(LoggerInterface $dbLogger): void
    {
        $this->logger = $dbLogger;
    }
}
