<?php

declare(strict_types=1);

namespace Bolt\Event\Listener;

use Bolt\Configuration\Config;
use Bolt\Entity\Taxonomy;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Events;

//#[AsDoctrineListener(event: Events::postLoad)]
readonly class TaxonomyFillListener
{
    public function __construct(private Config $config) {}

    public function postLoad(PostLoadEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof Taxonomy) {
            $this->fillTaxonomy($entity);
        }
    }

    public function fillTaxonomy(Taxonomy $entity): void
    {
        $entity->setDefinitionFromTaxonomyTypesConfig($this->config->get('taxonomies'));
    }
}
