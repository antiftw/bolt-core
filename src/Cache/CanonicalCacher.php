<?php

namespace Bolt\Cache;

use Bolt\Canonical;
use Bolt\Configuration\Config;
use Bolt\Twig\LocaleExtension;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class CanonicalCacher extends Canonical implements CachingInterface
{
    use CachingTrait;

    public function __construct(
        private readonly Config $config,
        private readonly UrlGeneratorInterface $urlGenerator,
        RequestStack $requestStack,
        private readonly RouterInterface $router,
        private readonly Stopwatch $stopwatch,
        private readonly TagAwareCacheInterface $cache,
        string $defaultLocale
    ) {
        parent::__construct($config, $urlGenerator, $requestStack, $router, $defaultLocale);
    }

    public const string CACHE_CONFIG_KEY = 'canonical';

    public function generateLink(?string $route, ?array $params, $canonical = false): ?string
    {
        $this->setCacheKey([$route, $canonical] + $params);

        return $this->execute([parent::class, __FUNCTION__], [$route, $params, $canonical]);
    }
}
