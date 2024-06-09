<?php

declare(strict_types=1);

namespace Bolt\Event\Listener;

use Bolt\Configuration\Config;
use Bolt\Entity\Relation;
use Doctrine\ORM\Event\PostLoadEventArgs;

readonly class RelationFillListener
{
    public function __construct(private Config $config) {}

    public function postLoad(PostLoadEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof Relation) {
            $this->fillRelation($entity);
        }
    }

    public function fillRelation(Relation $entity): void
    {
        if ($entity->getFromContent()->getDefinition() === null) {
            $entity->getFromContent()->setDefinitionFromContentTypesConfig($this->config->get('contenttypes'));
        }

        $entity->setDefinitionFromContentDefinition();
    }
}
