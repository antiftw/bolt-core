<?php

declare(strict_types=1);

namespace Bolt\Entity\Field;

use Bolt\Entity\Field;
use Bolt\Entity\FieldInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\DiscriminatorMap(value: [
    'email' => EmailField::class,
])]
class EmailField extends Field implements FieldInterface, ScalarCastable
{
    public const string TYPE = 'email';
}
