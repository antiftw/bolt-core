<?php

declare(strict_types=1);

namespace Bolt\Factory;

use Bolt\Configuration\Config;
use Bolt\Configuration\Content\ContentType;
use Bolt\Entity\Content;
use Bolt\Entity\User;
use Bolt\Event\Listener\ContentFillListener;
use Bolt\Security\ContentVoter;
use Bolt\Storage\Query;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;

readonly class ContentFactory
{
    public function __construct(
        private ContentFillListener $contentFillListener,
        private Security $security,
        private Query $query,
        private Config $config,
        private EntityManagerInterface $em
    ) {}

    public static function createStatic(ContentType $contentType): Content
    {
        $content = new Content($contentType);
        $content->setStatus($contentType->get('default_status'));

        return $content;
    }

    public function create(string $contentType): Content
    {
        /** @var ContentType $contentType */
        $contentType = $this->config->getContentType($contentType);

        $content = self::createStatic($contentType);

        $this->contentFillListener->fillContent($content);

        if ($this->security->getUser() !== null && $this->security->isGranted(ContentVoter::CONTENT_CREATE, $content)) {
            /** @var User $user */
            $user = $this->security->getUser();
            $content->setAuthor($user);
        }

        return $content;
    }

    /**
     * Fetch an existing record or create a new one,
     * based on the specified criteria (in setcontent-like format).
     */
    public function upsert(string $query, array $parameters = []): Content
    {
        $parameters['returnsingle'] = true;
        unset($parameters['returnmultiple']);

        $content = $this->query->getContent($query, $parameters);

        if (! $content instanceof Content) {
            /** @var ContentType $contentType */
            $contentType = $this->config->getContentType($query);
            $content = $this->create($contentType->getSlug());
        }

        return $content;
    }

    /**
     * @param Content|Content[] $content
     */
    public function save(Content|array $content): void
    {
        if ($content instanceof Content) {
            $this->em->persist($content);
        } elseif (is_iterable($content)) {
            foreach ($content as $c) {
                $this->em->persist($c);
            }
        }

        $this->em->flush();
    }
}
