<?php

namespace Bolt\Cache;

use Bolt\Configuration\Config;
use Bolt\Configuration\Content\ContentType;
use Bolt\Storage\Query;
use Bolt\Utils\ContentHelper;
use Bolt\Utils\RelatedOptionsUtility;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class RelatedOptionsUtilityCacher extends RelatedOptionsUtility implements CachingInterface
{
    use CachingTrait;

    public function __construct(
        Query $query,
        ContentHelper $contentHelper,
        UrlGeneratorInterface $router,
        private readonly TagAwareCacheInterface $cache,
        private readonly Stopwatch $stopwatch,
        private readonly Config $config
    ) {
        parent::__construct($query, $contentHelper, $router);
    }

    public const string CACHE_CONFIG_KEY = 'related_options';

    public function fetchRelatedOptions(ContentType $fromContentType, string $contentTypeSlug, string $order, string $format, bool $required, ?bool $allowEmpty, int $maxAmount, bool $linkToRecord): array
    {
        $this->setCacheKey([$contentTypeSlug, $order, $format, (string) $required, $maxAmount]);
        $this->setCacheTags($this->getTags($contentTypeSlug));

        return $this->execute([parent::class, __FUNCTION__], [$fromContentType, $contentTypeSlug, $order, $format, $required, $allowEmpty, $maxAmount, $linkToRecord]);
    }
}
