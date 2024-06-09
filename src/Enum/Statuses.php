<?php

declare(strict_types=1);

namespace Bolt\Enum;

use Tightenco\Collect\Support\Collection;

class Statuses
{
    public const string PUBLISHED = 'published';
    public const string HELD = 'held';
    public const string TIMED = 'timed';
    public const string DRAFT = 'draft';

    /**
     * @return string[]
     */
    public static function all(): array
    {
        return [
            static::PUBLISHED,
            static::HELD,
            static::TIMED,
            static::DRAFT,
        ];
    }

    public static function isValid(?string $status): bool
    {
        if ($status === null) {
            return false;
        }

        return (new Collection(static::all()))->containsStrict($status);
    }
}
