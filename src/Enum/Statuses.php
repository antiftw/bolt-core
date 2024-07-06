<?php

declare(strict_types=1);

namespace Bolt\Enum;

use Illuminate\Support\Collection;

enum Statuses: string {
    case PUBLISHED = 'published';
    case HELD = 'held';
    case TIMED = 'timed';
    case DRAFT = 'draft';

    public static function fromString(string $status): self
    {
        return match ($status) {
            'published' => self::PUBLISHED,
            'held' => self::HELD,
            'timed' => self::TIMED,
            'draft' => self::DRAFT,
            default => throw new \InvalidArgumentException("Invalid status: $status"),
        };
    }

    public static function isValid(?string $status): bool
    {
        if ($status === null) {
            return false;
        }

        return (new Collection(self::cases()))->containsStrict($status);
    }
}
