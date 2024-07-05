<?php

declare(strict_types=1);

namespace Bolt\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Illuminate\Support\Collection;

abstract class BaseFixture extends Fixture
{
    private array $referencesIndex = [];
    private array $taxonomyIndex = [];

    /**
     * During unit-tests, the fixtures are ran multiple times. Flush the
     * in-memory index, to prevent stale links to missing references.
     */
    protected function flushReferencesIndex(): void
    {
        $this->referencesIndex = [];
    }

    protected function getRandomReferenceWrong(string $fullyQualifiedClassName): object{
        $references = $this->referenceRepository->getReferencesByClass();
        $references = $references[$fullyQualifiedClassName];
        $randomReferenceKey = array_rand($references);
        return $references[$randomReferenceKey];
    }

    protected function getRandomReference(string $entityName)
    {
        $referenceName = $entityName;
        if (isset($this->referencesIndex[$entityName]) === false) {
            $this->referencesIndex[$entityName] = [];

            foreach ($this->referenceRepository->getReferencesByClass() as $class => $references) {
                foreach($references as $referenceName => $reference) {
                    if (mb_strpos($referenceName, $entityName . '_') === 0) {
                        $this->referencesIndex[$entityName][$referenceName] = $class;
                    }
                }
            }
        }
        if (empty($this->referencesIndex[$entityName])) {
            throw new \Exception(sprintf('Cannot find any references for Entity "%s"', $entityName));
        }

        $randomReferenceKey = array_rand($this->referencesIndex[$entityName]);

        return $this->getReference($randomReferenceKey, $this->referencesIndex[$entityName][$referenceName]);
    }


    protected function getRandomTaxonomies(string $type, int $amount): array
    {
        if (empty($this->taxonomyIndex)) {
            foreach (array_keys($this->referenceRepository->getReferencesByClass()) as $key) {
                if (mb_strpos($key, 'taxonomy_') === 0) {
                    $tuples = explode('_', $key);
                    $this->taxonomyIndex[$tuples[1]][] = $key;
                }
            }
        }

        if (empty($this->taxonomyIndex[$type])) {
            return [];
        }

        $taxonomies = [];

        foreach ((array) array_rand($this->taxonomyIndex[$type], $amount) as $key) {
            $taxonomies[] = $this->getReference($this->taxonomyIndex[$type][$key]);
        }

        return $taxonomies;
    }

    protected function getImagesIndex($path): Collection
    {
        $finder = $this->findFiles($path);

        $files = [];

        foreach ($finder as $file) {
            $files[$file->getFilename()] = $file;
        }

        return new Collection($files);
    }

    private function findFiles(string $base): Finder
    {
        $fullPath = Path::canonicalize($base);

        $glob = '*.{jpg,png,gif,jpeg,webp,avif}';

        $finder = new Finder();
        $finder->in($fullPath)->depth('< 3')->sortByName()->name($glob)->files();

        return $finder;
    }

    protected function getOption(string $name): bool
    {
        return in_array($name, $_SERVER['argv'], true);
    }
}
