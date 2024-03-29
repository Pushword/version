<?php

namespace Pushword\Version;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Pushword\Core\Entity\PageInterface;
// use Doctrine\ORM\Event\LifecycleEventArgs;
use Pushword\Core\Repository\Repository;
use Pushword\Core\Utils\Entity;

use function Safe\file_get_contents;
use function Safe\scandir;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
class Versionner
{
    private readonly Filesystem $fileSystem;

    public static bool $version = true;

    /**
     * @param class-string<PageInterface> $pageClass
     */
    public function __construct(
        private readonly string $logDir,
        private readonly string $pageClass,
        private readonly EntityManagerInterface $entityManager,
        private readonly SerializerInterface $serializer
    ) {
        $this->fileSystem = new Filesystem();
    }

    public function postPersist(PostPersistEventArgs $lifecycleEventArgs): void
    {
        $this->postUpdate($lifecycleEventArgs);
    }

    public function postUpdate(PostPersistEventArgs|PostUpdateEventArgs $lifecycleEventArgs): void
    {
        if (! static::$version) {
            return;
        }

        $entity = $lifecycleEventArgs->getObject();

        if (! $entity instanceof PageInterface) {
            return;
        }

        $this->createVersion($entity);
    }

    private function createVersion(PageInterface $page): void
    {
        $versionFile = $this->getVersionFile($page);

        $jsonContent = $this->serializer
            ->serialize($page, 'json', [AbstractNormalizer::ATTRIBUTES => $this->getProperties($page)]);

        $this->fileSystem->dumpFile($versionFile, $jsonContent);
    }

    public function loadVersion(string $pageId, string $version): void
    {
        static::$version = false;

        $page = Repository::getPageRepository($this->entityManager, $this->pageClass)->findOneBy(['id' => $pageId]);

        if (! $page instanceof PageInterface) {
            throw new \Exception('Page not found `'.$pageId.'`');
        }

        $this->populate($page, $version);

        $this->entityManager->flush();

        static::$version = true;
    }

    public function populate(PageInterface $page, string $version, ?int $pageId = null): PageInterface
    {
        $pageVersionned = $this->getPageVersion($pageId ?? $page, $version);

        $this->serializer->deserialize($pageVersionned, $page::class, 'json', [AbstractNormalizer::OBJECT_TO_POPULATE => $page]);

        return $page;
    }

    private function getPageVersion(int|PageInterface $page, string $version): string
    {
        $versionFile = $this->getVersionFile($page, $version);

        return file_get_contents($versionFile);
    }

    public function reset(int|PageInterface $pageId): void
    {
        $this->fileSystem->remove($this->getVersionDir($pageId));
    }

    /**
     * @return string[]
     */
    public function getPageVersions(int|PageInterface $page): array
    {
        $dir = $this->getVersionDir($page);
        if (! file_exists($dir)) {
            return [];
        }

        $scandir = scandir($dir);

        $versions = array_filter($scandir, static fn (string $item): bool => ! \in_array($item, ['.', '..'], true));

        return array_values($versions);
    }

    private function getVersionDir(int|PageInterface $page): string
    {
        $pageId = ($page instanceof PageInterface ? (string) $page->getId() : $page);

        return $this->logDir.'/version/'.$pageId;
    }

    private function getVersionFile(int|PageInterface $page, ?string $version = null): string
    {
        return $this->getVersionDir($page).'/'.($version ?? uniqid());
    }

    /**
     * @return array<string>
     */
    private function getProperties(PageInterface $page): array
    {
        return Entity::getProperties($page);
    }
}
