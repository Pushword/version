<?php

declare(strict_types=1);

namespace Pushword\Version\Tests;

use Pushword\Core\Entity\Page;
use Pushword\Version\Versionner;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;

class VersionTest extends KernelTestCase
{
    public function testIt(): void
    {
        self::bootKernel();

        $em = self::$kernel->getContainer()->get('doctrine.orm.default_entity_manager');

        $repo = $em->getRepository(Page::class);

        $page = $repo->findOneBy(['id' => 1]);

        $page->setH1('edited title to test Versioning');
        $em->flush();
        $page->setH1('edited title to test Versioning the second time');
        $em->flush();

        $versionner = new Versionner(
            self::$kernel->getLogDir(),
            self::$kernel->getContainer()->get('doctrine.orm.default_entity_manager'),
            new Serializer([], ['json' => new JsonEncoder()])
        );

        $pageVersions = $versionner->getPageVersions($page);

        $this->assertTrue(\count($pageVersions) >= 1);
    }
}
