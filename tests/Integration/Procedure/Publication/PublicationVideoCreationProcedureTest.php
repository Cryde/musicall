<?php

declare(strict_types=1);

namespace App\Tests\Integration\Procedure\Publication;

use App\ApiResource\Publication\Video\AddVideo;
use App\Contracts\Google\Youtube\YoutubeRepositoryInterface;
use App\Procedure\Publication\PublicationVideoCreationProcedure;
use App\Service\File\RemoteFileDownloader;
use App\Service\Google\DummyYoutubeRepository;
use App\Tests\Factory\Publication\PublicationSubCategoryFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class PublicationVideoCreationProcedureTest extends KernelTestCase
{
    use ResetDatabase, Factories;

    private const string VIDEO_URL = 'https://www.youtube.com/watch?v=' . DummyYoutubeRepository::VIDEO_ID_PROCEDURE_TEST;

    protected function setUp(): void
    {
        self::bootKernel();
        parent::setUp();

        self::getContainer()->set(YoutubeRepositoryInterface::class, new DummyYoutubeRepository());
    }

    public function test_process(): void
    {
        $this->mockRemoteFileDownloader();

        $user = UserFactory::new()->asBaseUser()->create()->_real();
        $category = PublicationSubCategoryFactory::new()->asDecouvertes()->create();

        $addVideo = new AddVideo();
        $addVideo->url = self::VIDEO_URL;
        $addVideo->title = 'The video title';
        $addVideo->description = 'The video description';

        $result = $this->getPublicationVideoCreationProcedure()->process($addVideo, $user);

        $this->assertSame('The video title', $result->getTitle());
        $this->assertSame('The video description', $result->getShortDescription());
        $this->assertNull($result->getDescription());
        $this->assertSame('images/publication/cover/max_res_url_path', $result->getCover()->getImageName());
        $this->assertSame(12345, $result->getCover()->getImageSize());
        $this->assertSame(2, $result->getType()); // 2 = Publication::TYPE_VIDEO
        $this->assertSame(1, $result->getStatus()); // 1 = Publication::STATUS_ONLINE
        $this->assertSame('v-the-video-title', $result->getSlug());
        $this->assertSame($category->_real()->getSlug(), $result->getSubCategory()->getSlug());
        $this->assertNotNull($result->getViewCache());
        $this->assertNotNull($result->getThread());
    }

    public function test_process_with_non_course_category(): void
    {
        $this->mockRemoteFileDownloader();

        $user = UserFactory::new()->asBaseUser()->create()->_real();
        $category = PublicationSubCategoryFactory::new()->asDecouvertes()->create();
        $news = PublicationSubCategoryFactory::new()->asNews()->create();

        $addVideo = new AddVideo();
        $addVideo->url = self::VIDEO_URL;
        $addVideo->title = 'The video title';
        $addVideo->description = 'The video description';
        $addVideo->category = $news->_real(); // only "course" category are accepted

        $result = $this->getPublicationVideoCreationProcedure()->process($addVideo, $user);

        $this->assertSame('The video title', $result->getTitle());
        $this->assertSame('The video description', $result->getShortDescription());
        $this->assertNull($result->getDescription());
        $this->assertSame('images/publication/cover/max_res_url_path', $result->getCover()->getImageName());
        $this->assertSame(12345, $result->getCover()->getImageSize());
        $this->assertSame(2, $result->getType()); // 2 = Publication::TYPE_VIDEO
        $this->assertSame(1, $result->getStatus()); // 1 = Publication::STATUS_ONLINE
        $this->assertSame('v-the-video-title', $result->getSlug());
        $this->assertSame($category->_real()->getSlug(), $result->getSubCategory()->getSlug());
        $this->assertNotNull($result->getViewCache());
        $this->assertNotNull($result->getThread());
    }

    private function mockRemoteFileDownloader(): void
    {
        $mock = $this->createMock(RemoteFileDownloader::class);
        $mock->expects($this->once())
            ->method('download')
            ->with('max_res_url', 'images/publication/cover')
            ->willReturn([
                'images/publication/cover/max_res_url_path',
                12345,
            ]);

        self::getContainer()->set(RemoteFileDownloader::class, $mock);
    }

    private function getPublicationVideoCreationProcedure(): PublicationVideoCreationProcedure
    {
        return self::getContainer()->get(PublicationVideoCreationProcedure::class);
    }
}
