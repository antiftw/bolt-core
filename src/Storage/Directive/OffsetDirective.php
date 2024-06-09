<?php

declare(strict_types=1);

namespace Bolt\Storage\Directive;

use Bolt\Storage\SelectQuery;

/**
 *  Directive to add a paging modifier to the query.
 */
class OffsetDirective
{
    public const string NAME = 'paging';

    public function __invoke(SelectQuery $query, int $page, array $otherDirectives): void
    {
        $limit = $otherDirectives['limit'] ?: 0;
        $query->getQueryBuilder()->setFirstResult(($page - 1) * $limit);
    }
}
