<?php

namespace App\Tests\Integration\Repository\Publication;

use App\Entity\Publication;
use App\Repository\PublicationRepository;
use App\Tests\Factory\Publication\PublicationFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class PublicationRepositoryTest extends KernelTestCase
{
    use ResetDatabase, Factories;

    public function test_find_one_video(): void
    {
        $repo = static::getContainer()->get(PublicationRepository::class);

        $video = PublicationFactory::createOne(['content' => 'content-id', 'type' => Publication::TYPE_VIDEO]);
        PublicationFactory::createOne(['content' => 'content-id', 'type' => Publication::TYPE_TEXT]);

        $result = $repo->findOneVideo('content-id');
        $this->assertSame($video->_real(), $result);
    }
}