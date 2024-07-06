<?php

declare(strict_types=1);

namespace Bolt\Enum;

use Illuminate\Support\Collection;

enum UserStatus : string {
    case ENABLED = 'enabled';
    case DISABLED = 'disabled';

    public static function isValid(?string $status): bool
    {
        if ($status === null) {
            return false;
        }

        return (new Collection(self::cases()))->containsStrict($status);
    }
}
