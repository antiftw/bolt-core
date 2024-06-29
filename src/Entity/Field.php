<?php

declare(strict_types=1);

namespace Bolt\Entity;

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GraphQl\Operation;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use Bolt\Common\Arr;
use Bolt\Configuration\Content\FieldType;
use Bolt\Event\Listener\FieldFillListener;
use Bolt\Utils\Sanitiser;
use Doctrine\ORM\Mapping as ORM;
use Knp\DoctrineBehaviors\Contract\Entity\TranslatableInterface;
use Knp\DoctrineBehaviors\Model\Translatable\TranslatableTrait;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Illuminate\Support\Collection;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;
use Twig\Markup;
#[ApiResource(
    uriTemplate: "/content/{contentId}/field/{id}",
    operations: [
        new Post(denormalizationContext: ['security' => "is_granted('api:post')"]),
        new Get(denormalizationContext: ['security' => "is_granted('api:get')"]),
        new Delete(denormalizationContext: ['security' => "is_granted('api:delete')"]),
    ],
    uriVariables: [ "id" => new Link(fromClass: Field::class), "companyId" => new Link(toProperty: 'content', fromClass: Content::class)],

    normalizationContext: ['groups' => ['get_field']],
    denormalizationContext: ['groups' => ['api_write'], 'enable_max_depth' => true],
    graphQlOperations: [
        new Operation(denormalizationContext: ["security" => "is_granted('api:get')"], name: "get"),
        new Operation(denormalizationContext: ["security" => "is_granted('api:post')"], name: "create"),
        new Operation(denormalizationContext: ["security" => "is_granted('api:delete')"], name: "delete"),
    ],
)]
//// todo-s7: not sure what to do with this
//    subresourceOperations: [
//        'api_contents_fields_get_subresource' => [
//            'method' => 'GET',
//        ],
//    ],
#[ApiFilter(SearchFilter::class)]
#[ORM\Entity(repositoryClass: "Bolt\Repository\FieldRepository")]
#[ORM\InheritanceType("SINGLE_TABLE")]
#[ORM\DiscriminatorColumn(name: "type", type: "string", length: 191)]
#[ORM\DiscriminatorMap(["generic" => "Field"])]
class Field implements FieldInterface, TranslatableInterface
{
    use TranslatableTrait;

    public const TYPE = 'generic';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 191)]
    #[Groups(['get_field', 'api_write'])]
    public string $name;

    #[ORM\Column(type: 'integer')]
    private int $sortorder = 0;

    #[ORM\Column(type: 'integer', nullable: true)]
    private int $version;

    #[ORM\ManyToOne(targetEntity: Content::class, fetch: "EAGER", inversedBy: "fields")]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['api_write'])]
    private Content $content;

    #[ORM\ManyToOne(targetEntity: Field::class, cascade: ["persist"])]
    #[ORM\JoinColumn(onDelete: "CASCADE")]
    private ?Field $parent = null;

    private ?FieldType $fieldTypeDefinition;
    private bool $useDefaultLocale = true;
    private static Sanitiser $sanitiser;
    private static Environment $twig;

    public function __toString(): string
    {
        $value = $this->getTwigValue();

        if (is_array($value)) {
            $value = implode('', Arr::flatten($value, PHP_INT_MAX));
        }

        return (string) $value;
    }

    public function __call(string $key = '', array $arguments = [])
    {
        $value = $this->getTwigValue();

        if (is_array($value) && array_key_exists($key, $value)) {
            // If value is field, return getTwigValue so that {{ value }}
            // is parsed as html, rather than __toString() which is escaped
            $value = $value[$key];

            return $value instanceof self ? $value->getTwigValue() : $value;
        }

        return null;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @Groups("get_field")
     */
    public function getDefinition(): FieldType
    {
        if ($this->fieldTypeDefinition === null) {
            $this->setDefinitionFromContentDefinition();
        }

        return $this->fieldTypeDefinition;
    }

    private function setDefinitionFromContentDefinition(): void
    {
        if ($this->getContent() && $this->getContent()->getDefinition()) {
            $contentTypeDefinition = $this->getContent()->getDefinition();
            $this->fieldTypeDefinition = FieldType::factory($this->getName(), $contentTypeDefinition);
        } else {
            $this->fieldTypeDefinition = FieldType::mock($this->getName());
        }
    }

    public function setDefinition($name, Collection $definition): void
    {
        $this->fieldTypeDefinition = FieldType::mock($name, $definition);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function get($key)
    {
        $default = $this->getDefaultValue();

        if ($this->isNew() && $default !== null) {
            if (! $default instanceof Collection) {
                throw new \RuntimeException('Default value of field ' . $this->getName() . ' is ' . gettype($default) . ' but it should be an array.');
            }

            return $this->getDefaultValue()->get($key);
        }

        if ($this->isTranslatable()) {
            return $this->translate($this->getCurrentLocale(), $this->useDefaultLocale())->get($key);
        }

        return $this->translate($this->getDefaultLocale(), false)->get($key);
    }

    /**
     * @Groups("get_field")
     * @SerializedName("value")
     */
    public function getApiValue()
    {
        if (! $this->isTranslatable()) {
            return $this->getParsedValue();
        }

        $result = [];

        $currentLocale = $this->getCurrentLocale();
        foreach ($this->getTranslations() as $translation) {
            $locale = $translation->getLocale();
            $this->setCurrentLocale($locale);
            $value = $this->getParsedValue();
            $result[$locale] = $value;
        }
        // restore current locale
        $this->setCurrentLocale($currentLocale);

        return $result;
    }

    public function getValue(): ?array
    {
        if ($this->isTranslatable()) {
            return $this->translate($this->getCurrentLocale(), $this->useDefaultLocale())->getValue();
        }

        return $this->translate($this->getDefaultLocale(), false)->getValue();
    }

    /**
     * Returns the default value option
     */
    public function getDefaultValue()
    {
        return $this->getDefinition()->get('default');
    }

    public function isNew(): bool
    {
        return $this->getId() === 0;
    }

    /** like getValue() but returns single value for single value fields */
    public function getParsedValue(): mixed
    {
        $value = $this->getValue();
        if (is_iterable($value)) {
            $count = count($value);
            if ($count === 0) {
                return null;
            } elseif ($count === 1 && array_keys($value)[0] === 0 && ! $this instanceof ListFieldInterface) {
                return reset($value);
            }
        }

        return $value;
    }

    public function getTwigValue(): string|array|Markup|bool
    {
        $value = $this->getParsedValue();

        if (is_string($value) && $this->getDefinition()->get('sanitise')) {
            $value = self::getSanitiser()->clean($value);
        }

        // Trim the zero spaces even before saving in FieldFillListener.
        // Otherwise, the preview contains zero width whitespace.
        $value = is_string($value) ? FieldFillListener::trimZeroWidthWhitespace($value) : $value;

        if ($this->shouldBeRenderedAsTwig($value)) {
            $valueBeforeRenderingAsTwig = $value;
            try {
                $template = self::getTwig()->createTemplate($value);
                $value    = $template->render([
                    // Edge case, if we try to generate a title or excerpt for a field that allows Twig
                    // and references {{ record }}
                    'record' => $this->getContent(),
                ]);
            } catch (LoaderError|SyntaxError) {
                // Prevent saving error (translations getting cleared if Twig code contains errors)
                $value = $valueBeforeRenderingAsTwig;
            }
        }

        if (is_string($value) && $this->getDefinition()->get('allow_html')) {
            $value = new Markup($value, 'UTF-8');
        }

        return $value;
    }

    private function shouldBeRenderedAsTwig($value): bool
    {
        return is_string($value) && $this->getDefinition()->get('allow_twig') && preg_match('/{[{%#]/', $value);
    }

    public function set(string $key, $value): self
    {
        $this->translate($this->getCurrentLocale(), ! $this->isTranslatable())->set($key, $value);

        return $this;
    }

    /**
     * @Groups("api_write")
     */
    public function setValue($value): self
    {
        $this->translate($this->getLocale(), ! $this->isTranslatable())->setValue($value);
        $this->mergeNewTranslations();

        return $this;
    }

    public function getSortorder(): ?int
    {
        return $this->sortorder;
    }

    public function setSortorder(int $sortorder): self
    {
        $this->sortorder = $sortorder;

        return $this;
    }

    public function setLocale(?string $locale): self
    {
        $this->setCurrentLocale($locale ?? $this->getDefaultLocale());

        return $this;
    }

    public function getLocale(): ?string
    {
        return $this->getCurrentLocale();
    }

    public function getVersion(): ?int
    {
        return $this->version;
    }

    public function setVersion(?int $version): self
    {
        $this->version = $version;

        return $this;
    }

    public function getContent(): ?Content
    {
        return $this->content;
    }

    public function setContent(?Content $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function hasParent(): bool
    {
        return $this->parent !== null;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): self
    {
        $this->parent = $parent;

        return $this;
    }

    public function setLabel(string $label): self
    {
        $this->getDefinition()->put('label', $label);

        return $this;
    }

    /**
     * @Groups("get_field")
     */
    public function getType(): string
    {
        return static::TYPE;
    }

    /**
     * Used in SelectField, to distinguish between selects for "a list of items"
     * and "select from a list of Content"
     */
    public function isContentSelect(): bool
    {
        return false;
    }

    /**
     * Used in TranslatableInterface, to locate the translation entity Bolt\Entity\FieldTranslation
     */
    public static function getTranslationEntityClass(): string
    {
        $explodedNamespace = explode('\\', self::class);
        $entityClass = array_pop($explodedNamespace);

        return '\\' . implode('\\', $explodedNamespace) . '\\' . $entityClass . 'Translation';
    }

    public function isTranslatable(): bool
    {
        return $this->getDefinition()->get('localize') === true;
    }

    public function useDefaultLocale(): bool
    {
        return $this->useDefaultLocale;
    }

    public function setUseDefaultLocale(bool $useDefaultLocale): void
    {
        $this->useDefaultLocale = $useDefaultLocale;
    }

    protected static function getSanitiser(): Sanitiser
    {
        return self::$sanitiser;
    }

    public static function setSanitiser(Sanitiser $sanitiser): void
    {
        self::$sanitiser = $sanitiser;
    }

    protected static function getTwig(): Environment
    {
        return self::$twig;
    }

    public static function setTwig(Environment $twig): void
    {
        self::$twig = $twig;
    }

    public function allowEmpty(): bool
    {
        return self::definitionAllowsEmpty($this->getDefinition());
    }

    public static function definitionAllowsEmpty(Collection $definition): bool
    {
        return self::settingsAllowEmpty(
            $definition->get('allow_empty'),
            $definition->get('required')
        );
    }

    /**
     * True if settings allow empty value.
     *
     * Settings priority:
     * - allow_empty
     * - required
     *
     * Defaults to true.
     */
    public static function settingsAllowEmpty(?bool $allowEmpty, ?bool $required): bool
    {
        if (null !== $allowEmpty) {
            return $allowEmpty;
        }

        if (null !== $required) {
            return !$required;
        }

        return true;
    }
}
