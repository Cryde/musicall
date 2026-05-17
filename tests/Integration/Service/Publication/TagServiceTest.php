<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service\Publication;

use App\Repository\Publication\TagRepository;
use App\Service\Publication\TagService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[\Zenstruck\Foundry\Attribute\ResetDatabase]
class TagServiceTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
        parent::setUp();
    }

    public function test_upsert_by_labels_dedupes_by_slug(): void
    {
        $this->getService()->upsertByLabels(['Metal', 'Interview']);
        $this->getEntityManager()->flush();
        $this->getService()->upsertByLabels(['metal', 'Interview', 'Tour']);
        $this->getEntityManager()->flush();

        $this->assertCount(3, $this->getRepository()->findAll(), 'Same slug (metal/Metal) must not create a duplicate row');
    }

    private function getService(): TagService
    {
        return self::getContainer()->get(TagService::class);
    }

    private function getRepository(): TagRepository
    {
        return self::getContainer()->get(TagRepository::class);
    }

    private function getEntityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
