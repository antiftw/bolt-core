<?php

declare(strict_types=1);

namespace Bolt\Entity;

use Bolt\Common\Str;
use Bolt\Configuration\Content\TaxonomyType;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: 'Bolt\Repository\TaxonomyRepository')]
class Taxonomy
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups('public')]
    private ?int $id = null;

    #[ORM\ManyToMany(targetEntity: Content::class, inversedBy: 'taxonomies')]
    private Collection $content;

    #[ORM\Column(length: 191)]
    #[Groups(['get_content', 'public'])]
    private string $type= '';

    #[ORM\Column(length: 191)]
    #[Groups(['get_content', 'public'])]
    private string $slug = '';

    #[ORM\Column(length: 191)]
    #[Groups(['get_content', 'public'])]
    private string $name = '';

    #[ORM\Column(name: 'sortorder')]
    #[Groups('public')]
    private int $sortOrder = 0;

    private ?TaxonomyType $taxonomyTypeDefinition = null;

    public function __construct(?TaxonomyType $taxonomyTypeDefinition = null)
    {
        $this->content = new ArrayCollection();

        if ($taxonomyTypeDefinition) {
            $this->setType($taxonomyTypeDefinition->getSlug());
            $this->setDefinition($taxonomyTypeDefinition);
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @see \Bolt\Event\Listener\TaxonomyFillListener
     */
    public function setDefinitionFromTaxonomyTypesConfig(\Illuminate\Support\Collection $taxonomyTypesConfig): void
    {
        $this->taxonomyTypeDefinition = TaxonomyType::factory($this->type, $taxonomyTypesConfig);
    }

    public function getContent(): Collection
    {
        return $this->content;
    }

    public function addContent(Content $content): self
    {
        if (! $this->content->contains($content)) {
            $this->content[] = $content;
        }

        return $this;
    }

    public function removeContent(Content $content): self
    {
        if ($this->content->contains($content)) {
            $this->content->removeElement($content);
        }

        return $this;
    }

    public function getTaxonomyTypeSlug(): string
    {
        if ($this->getDefinition() === null) {
            return $this->getType();
        }

        return $this->getDefinition()->get('slug');
    }

    public function getTaxonomyTypeSingularSlug(): string
    {
        if ($this->getDefinition() === null) {
            return $this->getType();
        }

        return $this->getDefinition()->get('singular_slug');
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = Str::slug($slug);

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getSortOrder(): ?int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): self
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    public function setDefinition(TaxonomyType $taxonomyType): void
    {
        $this->taxonomyTypeDefinition = $taxonomyType;
    }

    /**
     * @Groups("get_definition")
     */
    public function getDefinition(): ?TaxonomyType
    {
        return $this->taxonomyTypeDefinition;
    }
}
