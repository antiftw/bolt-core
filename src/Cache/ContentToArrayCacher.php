<?php

namespace Bolt\Cache;

use Bolt\Configuration\Config;
use Bolt\Entity\Content;
use Bolt\Twig\JsonExtension;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Stopwatch\Stopwatch;

class ContentToArrayCacher extends JsonExtension implements CachingInterface
{
    use CachingTrait;

    public const string CACHE_CONFIG_KEY = 'content_array';

    public function __construct(
        private readonly NormalizerInterface $normalizer,
        private readonly Stopwatch $stopwatch,
        private readonly Config $config
    )
    {
        parent::__construct($normalizer, $stopwatch);
    }

    protected function contentToArray(Content $content, string $locale = ''): array
    {
        $this->setCacheKey([$content->getCacheKey($locale)]);
        $this->setCacheTags([$content->getCacheKey()]);

        return $this->execute([parent::class, __FUNCTION__], [$content, $locale]);
    }
}
