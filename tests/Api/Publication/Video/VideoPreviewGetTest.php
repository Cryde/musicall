<?php

declare(strict_types=1);

namespace App\Tests\Api\Publication\Video;

use App\Contracts\Google\Youtube\YoutubeRepositoryInterface;
use App\Entity\Publication;
use App\Service\Google\DummyYoutubeRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Metric\ViewCacheFactory;
use App\Tests\Factory\Publication\PublicationFactory;
use App\Tests\Factory\Publication\PublicationSubCategoryFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class VideoPreviewGetTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    private const string VALID_VIDEO_ID = DummyYoutubeRepository::VIDEO_ID_RICK_ASTLEY;
    private const string VALID_YOUTUBE_URL = 'https://www.youtube.com/watch?v=' . self::VALID_VIDEO_ID;
    private const string NON_EXISTING_VIDEO_ID = DummyYoutubeRepository::VIDEO_ID_NON_EXISTING;
    private const string SHORT_VIDEO_ID = DummyYoutubeRepository::VIDEO_ID_SHORT_VIDEO;

    protected function setUp(): void
    {
        parent::setUp();

        self::getContainer()->set(YoutubeRepositoryInterface::class, new DummyYoutubeRepository());
    }

    public function test_get_video_preview_success(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user->_real());
        $this->client->request(
            'GET',
            '/api/publications/video/preview?url=' . urlencode(self::VALID_YOUTUBE_URL),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/VideoPreview',
            '@id' => '/api/publications/video/preview',
            '@type' => 'VideoPreview',
            'url' => 'https://www.youtube.com/watch?v=' . self::VALID_VIDEO_ID,
            'title' => 'Never Gonna Give You Up',
            'description' => 'The official video for Rick Astley',
            'image_url' => 'https://i.ytimg.com/vi/dQw4w9WgXcQ/maxresdefault.jpg',
        ]);
    }

    public function test_get_video_preview_without_authentication_fails(): void
    {
        $this->client->request(
            'GET',
            '/api/publications/video/preview?url=' . urlencode(self::VALID_YOUTUBE_URL),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code' => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function test_get_video_preview_without_url_fails(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user->_real());
        $this->client->request(
            'GET',
            '/api/publications/video/preview',
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'url',
                    'message' => 'Cette valeur ne doit pas être vide.',
                    'code' => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                ],
            ],
            'detail' => 'url: Cette valeur ne doit pas être vide.',
            'title' => 'An error occurred',
            'description' => 'url: Cette valeur ne doit pas être vide.',
            'type' => '/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
        ]);
    }

    public function test_get_video_preview_with_invalid_string_fails(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user->_real());
        $this->client->request(
            'GET',
            '/api/publications/video/preview?url=carot',
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/57c2f299-1154-4870-89bb-ef3b1f5ad229',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'url',
                    'message' => 'Cette valeur n\'est pas une URL valide.',
                    'code' => '57c2f299-1154-4870-89bb-ef3b1f5ad229',
                ],
            ],
            'detail' => 'url: Cette valeur n\'est pas une URL valide.',
            'title' => 'An error occurred',
            'description' => 'url: Cette valeur n\'est pas une URL valide.',
            'type' => '/validation_errors/57c2f299-1154-4870-89bb-ef3b1f5ad229',
        ]);
    }

    public function test_get_video_preview_with_non_youtube_url_fails(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user->_real());
        $this->client->request(
            'GET',
            '/api/publications/video/preview?url=' . urlencode('https://google.com'),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/music_all_f03dc5f4-8ba0-11ee-b9d1-0242ac120002',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'url',
                    'message' => 'L\'url de cette vidéo n\'est pas supportée',
                    'code' => 'music_all_f03dc5f4-8ba0-11ee-b9d1-0242ac120002',
                ],
            ],
            'detail' => 'url: L\'url de cette vidéo n\'est pas supportée',
            'title' => 'An error occurred',
            'description' => 'url: L\'url de cette vidéo n\'est pas supportée',
            'type' => '/validation_errors/music_all_f03dc5f4-8ba0-11ee-b9d1-0242ac120002',
        ]);
    }

    public function test_get_video_preview_with_already_existing_video_fails(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $author = UserFactory::new()->asAdminUser()->create();
        $subCategory = PublicationSubCategoryFactory::new()->asDecouvertes()->create();

        PublicationFactory::new([
            'author' => $author,
            'content' => self::VALID_VIDEO_ID,
            'creationDatetime' => new \DateTime(),
            'publicationDatetime' => new \DateTime(),
            'shortDescription' => 'A video description',
            'slug' => 'existing-video',
            'status' => Publication::STATUS_ONLINE,
            'subCategory' => $subCategory,
            'title' => 'Existing Video',
            'type' => Publication::TYPE_VIDEO,
            'viewCache' => ViewCacheFactory::new(['count' => 10])->create(),
        ])->create();

        $this->client->loginUser($user->_real());
        $this->client->request(
            'GET',
            '/api/publications/video/preview?url=' . urlencode(self::VALID_YOUTUBE_URL),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/music_all_99153e73-dd44-4557-90aa-3c0e354fce62',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'url',
                    'message' => 'Cette vidéo existe déjà sur MusicAll',
                    'code' => 'music_all_99153e73-dd44-4557-90aa-3c0e354fce62',
                ],
            ],
            'detail' => 'url: Cette vidéo existe déjà sur MusicAll',
            'title' => 'An error occurred',
            'description' => 'url: Cette vidéo existe déjà sur MusicAll',
            'type' => '/validation_errors/music_all_99153e73-dd44-4557-90aa-3c0e354fce62',
        ]);
    }

    public function test_get_video_preview_with_non_existing_youtube_video_fails(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user->_real());
        $this->client->request(
            'GET',
            '/api/publications/video/preview?url=' . urlencode('https://www.youtube.com/watch?v=' . self::NON_EXISTING_VIDEO_ID),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Video not found',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Video not found',
        ]);
    }

    public function test_get_video_preview_with_empty_url_parameter_fails(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user->_real());
        $this->client->request(
            'GET',
            '/api/publications/video/preview?url=',
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'url',
                    'message' => 'Cette valeur ne doit pas être vide.',
                    'code' => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                ],
            ],
            'detail' => 'url: Cette valeur ne doit pas être vide.',
            'title' => 'An error occurred',
            'description' => 'url: Cette valeur ne doit pas être vide.',
            'type' => '/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
        ]);
    }

    public function test_get_video_preview_with_youtube_short_url_success(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user->_real());
        $this->client->request(
            'GET',
            '/api/publications/video/preview?url=' . urlencode('https://youtu.be/' . self::SHORT_VIDEO_ID),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertJsonEquals([
            '@type' => 'VideoPreview',
            'title' => 'Short Video',
            '@context' => '/api/contexts/VideoPreview',
            '@id' => '/api/publications/video/preview',
            'url' => 'https://www.youtube.com/watch?v=shortVidId',
            'description' => 'A short video description',
            'image_url' => 'https://i.ytimg.com/vi/shortVidId/maxresdefault.jpg',
        ]);
    }
}
