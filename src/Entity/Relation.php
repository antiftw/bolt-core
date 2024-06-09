<?php

declare(strict_types=1);

namespace Bolt\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use Bolt\Repository\RelationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
#[ApiResource(
    collectionOperations: [
        "get" => ["security" => "is_granted('api:get')"],
        "post" => ["security" => "is_granted('api:post')"]
    ],
    graphql: [
        "item_query" => ["security" => "is_granted('api:get')"],
        "collection_query" => ["security" => "is_granted('api:get')"],
        "create" => ["security" => "is_granted('api:post')"],
        "delete" => ["security" => "is_granted('api:delete')"]
    ],
    itemOperations: [
        "get" => ["security" => "is_granted('api:get')"],
        "put" => ["security" => "is_granted('api:post')"],
        "delete" => ["security" => "is_granted('api:delete')"]
    ],
    normalizationContext: ["groups" => ["get_relation"]]
)]
#[ORM\Entity(repositoryClass: RelationRepository::class)]
#[ApiFilter(SearchFilter::class, strategy: 'partial')]
class Relation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Content::class, fetch: "EAGER", inversedBy: "relationsFromThisContent")]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    #[Groups("get_relation")]
    private Content $fromContent;

    #[ORM\ManyToOne(targetEntity: Content::class, fetch: "EAGER", inversedBy: "relationsToThisContent")]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    #[Groups("get_relation")]
    private Content $toContent;

    #[ORM\Column(type: 'integer')]
    private int $position = 0;

    /**
     * Definition contains properties like:
     * - name
     * - from content type
     * - to content type(s)
     * - multiple
     * - sortable
     * - min
     * - max
     */
    private array $definition = [];

    public function __construct(Content $fromContent, Content $toContent)
    {
        $this->fromContent = $fromContent;
        $this->toContent = $toContent;
        $this->setDefinitionFromContentDefinition();
        // link other side of relation - needed for code using relations
        // from the content side later (e.g. validation)
        $fromContent->addRelationsFromThisContent($this);
        $toContent->addRelationsToThisContent($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function getFromContent(): ?Content
    {
        return $this->fromContent;
    }

    public function setFromContent($content): void
    {
        $this->fromContent = $content;
    }

    public function getToContent(): ?Content
    {
        return $this->toContent;
    }

    public function setToContent($content): void
    {
        $this->toContent = $content;
    }

    public function getDefinition(): array
    {
        if (empty($this->definition) && $this->fromContent instanceof Content) {
            $this->setDefinitionFromContentDefinition();
        }

        return $this->definition;
    }

    /**
     * @see: Bolt\Event\Listener\RelationFillListener
     */
    public function setDefinitionFromContentDefinition(): void
    {
        $contentTypeDefinition = $this->fromContent->getDefinition();
        if ($contentTypeDefinition === null) {
            throw new \InvalidArgumentException('Owning Content not fully initialized');
        }

        if (isset($contentTypeDefinition['relations'][$this->toContent->getContentTypeSlug()]) === false) {
            throw new \InvalidArgumentException('Invalid Relation name');
        }

        $this->definition = $contentTypeDefinition['relations'][$this->toContent->getContentTypeSlug()];
    }
}
