<?php

namespace Bolt\Cache;

use Bolt\Canonical;
use Bolt\Configuration\Config;
use Bolt\Entity\Content;
use Bolt\Twig\LocaleExtension;
use Bolt\Utils\ContentHelper;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
class GetFormatCacher extends ContentHelper implements CachingInterface
{
    use CachingTrait;

    public const string CACHE_CONFIG_KEY = 'formatter';

    public function get(Content $record, string $format = '', ?string $locale = null): string
    {
        $this->setCacheKey([$record->getId(), $format, $locale]);
        $this->setCacheTags($this->getTags($record->getContentTypeSlug()));

        return $this->execute([parent::class, __FUNCTION__], [$record, $format, $locale]);
    }
}
