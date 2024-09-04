<?php

namespace Bolt\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Bolt\Configuration\Config;
use Bolt\Configuration\Content\FieldType;
use Bolt\Entity\Content;
use Bolt\Repository\FieldRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

readonly class ContentStateProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')] private ProcessorInterface $decorated, // The previous decorator logic
        private Config $config,                // Configuration object
    ) {}

    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        // Ensure we are processing the correct entity
        if ($data instanceof Content) {
            $contentTypes = $this->config->get('contenttypes');

            $data->setDefinitionFromContentTypesConfig($contentTypes);

            foreach ($data->getFields() as $field) {
                $fieldDefinition = FieldType::factory($field->getName(), $data->getDefinition());
                $newField = FieldRepository::factory($fieldDefinition);

                $newField->setName($field->getName());
                $newField->setValue($field->getValue());

                $data->removeField($field);
                $data->addField($newField);
            }
        }

        // Persist the changes using the decorated processor
        $this->decorated->process($data, $operation, $uriVariables, $context);
    }
}
