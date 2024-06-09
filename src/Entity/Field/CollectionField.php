<?php

declare(strict_types=1);

namespace Bolt\Entity\Field;

use Bolt\Configuration\Content\ContentType;
use Bolt\Entity\Field;
use Bolt\Entity\FieldInterface;
use Bolt\Entity\FieldParentInterface;
use Bolt\Entity\FieldParentTrait;
use Bolt\Entity\IterableFieldTrait;
use Bolt\Entity\ListFieldInterface;
use Bolt\Repository\FieldRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class CollectionField extends Field implements Excerptable, FieldInterface, FieldParentInterface, ListFieldInterface, RawPersistable, \Iterator
{
    use FieldParentTrait;
    use IterableFieldTrait;

    public const string TYPE = 'collection';

    public function getTemplates(): array
    {
        $fieldDefinitions = $this->getDefinition()->get('fields', []);
        $result = [];

        foreach ($fieldDefinitions as $fieldName => $fieldDefinition) {
            $templateField = FieldRepository::factory($fieldDefinition, '', $fieldName);
            $templateField->setDefinition($fieldName, $this->getDefinition()->get('fields')[$fieldName]);
            $templateField->setName($fieldName);
            $result[$fieldName] = $templateField;
        }

        return $result;
    }

    public function getApiValue(): array
    {
        $fields = $this->getValue();
        $result = [];

        foreach ($fields as $field) {
            $result[] = [
                'name' => $field->getName(),
                'type' => $field->getType(),
                'value' => $field->getApiValue(),
            ];
        }

        return $result;
    }

    /**
     * @param FieldInterface[] $value
     */
    public function setValue($value): Field
    {
        /** @var Field $field */
        foreach ($value as $field) {
            // todo: This should be able to handle an array of fields
            // in key-value format, not just Field.php types.
            $field->setParent($this);
        }

        $this->fields = $value;

        return $this;
    }

    public function getValue(): ?array
    {
        return $this->fields;
    }

    public function getDefaultValue(): array
    {
        $default = parent::getDefaultValue();

        if ($default === null) {
            return [];
        }

        $result = [];

        /** @var ContentType $type */
        foreach ($default as $type) {
            $value = $type->toArray()['default'];
            $name = $type->toArray()['field'];
            $definition = $this->getDefinition()->get('fields')[$name];
            $field = FieldRepository::factory($definition, $name);
            $field->setValue($value);
            $result[] = $field;
        }

        return $result;
    }

    public function __toString(): string
    {
        $fields = $this->getValue();
        $result = [];

        foreach ($fields as $field) {
            if ($field instanceof Excerptable) {
                $result[] = $field->__toString();
            }
        }

        return implode(" ", $result);
    }
}
