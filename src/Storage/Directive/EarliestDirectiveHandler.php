<?php

declare(strict_types=1);

namespace Bolt\Storage\Directive;

use Bolt\Storage\QueryInterface;

/**
 *  Directive to modify query based on activation of 'earliest' modifier.
 *
 *  eg: {% setcontent pages = 'pages' earliest %}
 */
class EarliestDirectiveHandler
{
    public const string NAME = 'earliest';

    public function __invoke(QueryInterface $query, $value, &$directives): void
    {
        $directives[OrderDirective::NAME] = 'id';
    }
}
