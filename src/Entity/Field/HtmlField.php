<?php

declare(strict_types=1);

namespace Bolt\Entity\Field;

use Bolt\Entity\Field;
use Bolt\Entity\FieldInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\DiscriminatorMap(value: [
    'html' => HtmlField::class,
])]
class HtmlField extends Field implements Excerptable, FieldInterface
{
    public const string TYPE = 'html';
}
