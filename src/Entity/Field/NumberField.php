<?php

declare(strict_types=1);

namespace Bolt\Entity\Field;

use Bolt\Entity\Field;
use Bolt\Entity\FieldInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\DiscriminatorMap(value: [
    'number' => NumberField::class,
])]
class NumberField extends Field implements FieldInterface, ScalarCastable
{
    public const string TYPE = 'number';

    public function getValue(): ?array
    {
        $pv = parent::getValue();

        return empty($pv) ? $pv : [(float) $pv[0]];
    }
}
