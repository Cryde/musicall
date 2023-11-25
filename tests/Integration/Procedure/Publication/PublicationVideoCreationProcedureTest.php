<?php

namespace App\Tests\Integration\Procedure\Publication;

use App\Entity\Publication;
use App\Model\Publication\Request\AddVideo;
use App\Procedure\Publication\PublicationVideoCreationProcedure;
use App\Service\Builder\CommentThreadDirector;
use App\Service\Builder\Metric\ViewCacheDirector;
use App\Service\Builder\PublicationCoverDirector;
use App\Service\Builder\PublicationDirector;
use App\Service\File\RemoteFileDownloader;
use App\Service\Google\GoogleApi;
use App\Service\Google\Youtube;
use App\Service\Google\YoutubeUrlHelper;
use App\Tests\Factory\Publication\PublicationSubCategoryFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Google\Client;
use Google\Service\YouTube as GoogleYouTube;
use Google\Service\YouTube\Thumbnail;
use Google\Service\YouTube\ThumbnailDetails;
use Google\Service\YouTube\Video;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class PublicationVideoCreationProcedureTest extends KernelTestCase
{
    use ResetDatabase, Factories;

    private PublicationVideoCreationProcedure $publicationVideoCreationProcedure;
    protected function setUp(): void
    {
        self::bootKernel();
        parent::setUp();

        $container = static::getContainer();


        $this->publicationVideoCreationProcedure = new PublicationVideoCreationProcedure(
            new Youtube($this->buildGoogleClientApiMock(), $container->get(YoutubeUrlHelper::class)),
            $container->get(PublicationCoverDirector::class),
            $container->get(PublicationDirector::class),
            $this->buildRemoteFileDownloader(),
            $container->get(ParameterBagInterface::class),
            $container->get(CommentThreadDirector::class),
            $container->get(ViewCacheDirector::class),
            $container->get(EntityManagerInterface::class),
        );
    }

    public function testProcess()
    {
        $user1 = UserFactory::new()->asBaseUser()->create()->object();
        $category = PublicationSubCategoryFactory::new()->asDecouvertes()->create();

        $addVideo = new AddVideo();
        $addVideo->url = 'https://www.youtube.com/watch?v=YudHcBIxlYw';
        $addVideo->title = 'The video title';
        $addVideo->description = 'The video description';

        $result = $this->publicationVideoCreationProcedure->process($addVideo, $user1);

        $this->assertSame('The video title', $result->getTitle());
        $this->assertSame('The video description', $result->getShortDescription());
        $this->assertNull($result->getDescription());
        $this->assertSame('images/publication/cover/max_res_url_path', $result->getCover()->getImageName());
        $this->assertSame(12345, $result->getCover()->getImageSize());
        $this->assertSame(2, $result->getType()); // 2 = Publication::TYPE_VIDEO
        $this->assertSame(1, $result->getStatus()); // 1 = Publication::STATUS_ONLINE
        $this->assertSame('v-the-video-title', $result->getSlug());
        $this->assertSame($category->object()->getSlug(), $result->getSubCategory()->getSlug());
        $this->assertNotNull($result->getViewCache());
        $this->assertNotNull($result->getThread());
    }

    public function testProcessWithNonCourseCategory()
    {
        $user1 = UserFactory::new()->asBaseUser()->create()->object();
        $category = PublicationSubCategoryFactory::new()->asDecouvertes()->create();
        $news = PublicationSubCategoryFactory::new()->asNews()->create();

        $addVideo = new AddVideo();
        $addVideo->url = 'https://www.youtube.com/watch?v=YudHcBIxlYw';
        $addVideo->title = 'The video title';
        $addVideo->description = 'The video description';
        $addVideo->category = $news->object(); // only "course" category are accepted

        $result = $this->publicationVideoCreationProcedure->process($addVideo, $user1);

        $this->assertSame('The video title', $result->getTitle());
        $this->assertSame('The video description', $result->getShortDescription());
        $this->assertNull($result->getDescription());
        $this->assertSame('images/publication/cover/max_res_url_path', $result->getCover()->getImageName());
        $this->assertSame(12345, $result->getCover()->getImageSize());
        $this->assertSame(2, $result->getType()); // 2 = Publication::TYPE_VIDEO
        $this->assertSame(1, $result->getStatus()); // 1 = Publication::STATUS_ONLINE
        $this->assertSame('v-the-video-title', $result->getSlug());
        $this->assertSame($category->object()->getSlug(), $result->getSubCategory()->getSlug());
        $this->assertNotNull($result->getViewCache());
        $this->assertNotNull($result->getThread());
    }

    private function buildRemoteFileDownloader()
    {
        $mock = $this->createMock(RemoteFileDownloader::class);

        $mock->expects($this->once())
            ->method('download')
            ->with('max_res_url', 'images/publication/cover')
            ->willReturn([
                'images/publication/cover/max_res_url_path',
                12345
            ]);

        return $mock;
    }

    private function buildGoogleClientApiMock()
    {
        // todo refactor the code (not this test yet) to avoid so many mock
        $youtubeMock = $this->createMock(GoogleYouTube::class);
        $listVideoMock = $this->createMock(GoogleYouTube\VideoListResponse::class);

        $snip = new GoogleYouTube\VideoSnippet();
        $snip->setTitle('titre de la vidéo');
        $snip->setDescription('description de la vidéo');
        $thumb = new ThumbnailDetails;
        $res = (new Thumbnail());
        $res->setUrl('max_res_url');
        $thumb->setMaxres($res);
        $snip->setThumbnails($thumb);
        $youtubeVideo =   new Video();
        $youtubeVideo->setSnippet($snip);
        $listVideoMock->items = [$youtubeVideo];
        $videoMock = $this->createMock(GoogleYouTube\Resource\Videos::class);

        $videoMock
            ->expects($this->once())
            ->method('listVideos')
            ->with('snippet', ['id' => "YudHcBIxlYw"])
            ->willReturn($listVideoMock);
        $youtubeMock->videos = $videoMock;
        $googleApiMock = $this->createMock(GoogleApi::class);
        $googleApiMock
            ->expects($this->once())
            ->method('getYoutube')
            ->willReturn($youtubeMock);

        return $googleApiMock;
    }
}