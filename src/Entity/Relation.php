<?php

declare(strict_types=1);

namespace Bolt\Entity;

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GraphQl\Operation;
use ApiPlatform\Metadata\Post;
use Bolt\Repository\RelationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
#[ApiResource(
    operations: [
        new Get(denormalizationContext: ["security" => "is_granted('api:get')"]),
        new Post(denormalizationContext: ["security" => "is_granted('api:post')"]),
        new Delete(denormalizationContext: ["security" => "is_granted('api:delete')"]),
    ],
    normalizationContext: ["groups" => ["get_relation"]],
    graphQlOperations: [
        new Operation(denormalizationContext: ["security" => "is_granted('api:get')"], name: "get"),
        new Operation(denormalizationContext: ["security" => "is_granted('api:post')"], name: "create"),
        new Operation(denormalizationContext: ["security" => "is_granted('api:delete')"], name: "delete"),
    ]
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
