<?php

namespace Bolt\Cache;

use Bolt\Configuration\Content\ContentType;
use Bolt\Utils\RelatedOptionsUtility;

class RelatedOptionsUtilityCacher extends RelatedOptionsUtility implements CachingInterface
{
    use CachingTrait;

    public const string CACHE_CONFIG_KEY = 'related_options';

    public function fetchRelatedOptions(ContentType $fromContentType, string $toContentTypeSlug, string $order, string $format, bool $required, ?bool $allowEmpty, int $maxAmount, bool $linkToRecord): array
    {
        $this->setCacheKey([$toContentTypeSlug, $order, $format, (string) $required, $maxAmount]);
        $this->setCacheTags($this->getTags($toContentTypeSlug));

        return $this->execute([parent::class, __FUNCTION__], [$fromContentType, $toContentTypeSlug, $order, $format, $required, $allowEmpty, $maxAmount, $linkToRecord]);
    }
}
