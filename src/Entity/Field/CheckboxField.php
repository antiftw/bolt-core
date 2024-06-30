<?php

declare(strict_types=1);

namespace Bolt\Entity\Field;

use Bolt\Entity\Field;
use Bolt\Entity\FieldInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]

class CheckboxField extends Field implements FieldInterface, ScalarCastable, RawPersistable
{
    public const string TYPE = 'checkbox';

    public function setValue($value): Field
    {
        // Make sure we don't have arrays
        if (is_array($value)) {
            $value = current($value);
        }

        $value = match ($value) {
            'true' => true,
            'false' => false,
            default => (bool)$value,
        };

        return parent::setValue($value);
    }

    public function getTwigValue(): bool
    {
        return (bool)current($this->getValue());
    }
}
