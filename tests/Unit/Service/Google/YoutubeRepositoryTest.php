<?php

namespace App\Tests\Unit\Service\Google;

use App\Service\Google\YoutubeRepository;
use Google\Service\YouTube as GoogleYouTube;
use Google\Service\YouTube\Thumbnail;
use Google\Service\YouTube\ThumbnailDetails;
use Google\Service\YouTube\Video;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class YoutubeRepositoryTest extends TestCase
{
    public function testFetchVideoData(): void
    {
        $repository = new YoutubeRepository($this->buildGoogleClientApiMock());

        $this->assertSame([
            'title' => 'titre de la vidéo',
            'description' => 'description de la vidéo',
            'thumbnails' => [
                'maxres' => 'max_res_url',
                'high' => 'high_res_url',
            ]
        ], $repository->fetchVideoData('YudHcBIxlYw'));
    }

    public function testFetchVideoDataNotFound(): void
    {
        $repository = new YoutubeRepository($this->buildGoogleClientApiMockForNotFound());

        $this->assertNull($repository->fetchVideoData('not-found'));
    }


    private function buildGoogleClientApiMock(): Stub&GoogleYouTube
    {
        $youtubeMock = $this->createStub(GoogleYouTube::class);
        $listVideoMock = $this->createStub(GoogleYouTube\VideoListResponse::class);

        $snip = new GoogleYouTube\VideoSnippet();
        $snip->setTitle('titre de la vidéo');
        $snip->setDescription('description de la vidéo');
        $thumb = new ThumbnailDetails;
        $res1 = (new Thumbnail());
        $res1->setUrl('max_res_url');
        $res2 = (new Thumbnail());
        $res2->setUrl('high_res_url');
        $thumb->setMaxres($res1);
        $thumb->setHigh($res2);
        $snip->setThumbnails($thumb);
        $youtubeVideo =   new Video();
        $youtubeVideo->setSnippet($snip);
        $listVideoMock
            ->method('getItems')
            ->willReturn([$youtubeVideo]);
        $videoMock = $this->createMock(GoogleYouTube\Resource\Videos::class);

        $videoMock
            ->expects($this->once())
            ->method('listVideos')
            ->with('snippet', ['id' => "YudHcBIxlYw"])
            ->willReturn($listVideoMock);
        $youtubeMock->videos = $videoMock;

        return $youtubeMock;
    }

    private function buildGoogleClientApiMockForNotFound(): Stub&GoogleYouTube
    {
        $youtubeMock = $this->createStub(GoogleYouTube::class);
        $listVideoMock = $this->createStub(GoogleYouTube\VideoListResponse::class);

        $listVideoMock
            ->method('getItems')
            ->willReturn([]);
        $videoMock = $this->createMock(GoogleYouTube\Resource\Videos::class);
        $videoMock
            ->expects($this->once())
            ->method('listVideos')
            ->with('snippet', ['id' => "not-found"])
            ->willReturn($listVideoMock);
        $youtubeMock->videos = $videoMock;

        return $youtubeMock;
    }
}
