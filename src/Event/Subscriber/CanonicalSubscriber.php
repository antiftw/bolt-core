<?php

namespace Bolt\Event\Subscriber;

use Bolt\Canonical;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

readonly class CanonicalSubscriber implements EventSubscriberInterface
{
    public function __construct(private Canonical $canonical) {}

    public function onKernelRequest(): void
    {
        // ensure initialization with real request
        $this->canonical->getRequest();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [
                ['onKernelRequest', 0],
            ],
        ];
    }
}
