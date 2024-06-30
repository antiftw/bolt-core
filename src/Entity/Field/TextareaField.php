<?php

declare(strict_types=1);

namespace Bolt\Entity\Field;

use Bolt\Entity\Field;
use Bolt\Entity\FieldInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\DiscriminatorMap(value: [
    'text-area' => TextareaField::class,
])]
class TextareaField extends Field implements Excerptable, FieldInterface
{
    public const string TYPE = 'textarea';
}
