<?php

declare(strict_types=1);

namespace App\Tests\Api\Profile;

use App\Entity\Profile\ProfilePost;
use App\Entity\User;
use App\Repository\Profile\ProfilePostRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\User\UserFactory;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/** Posting on your own wall (#1316). */
#[ResetDatabase]
class ProfilePostCreateTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        $this->temporaryFiles = [];

        parent::tearDown();
    }

    public function test_post_a_text(): void
    {
        $author = UserFactory::new()->asBaseUser()->create(['username' => 'julien']);

        $this->client->loginUser($author);
        $this->client->jsonRequest('POST', '/api/user/profile-posts', [
            'content' => '  Le clip live est en ligne !  ',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $post = $this->onlyPostOf($author);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProfilePost',
            '@id' => '/api/user/profile-posts/' . $post->id,
            '@type' => 'ProfilePost',
            'id' => $post->id,
            'author' => [
                '@type' => 'ProfilePostAuthor',
                'username' => 'julien',
                'display_name' => 'julien',
            ],
            'content' => 'Le clip live est en ligne !',
            'photos' => [],
            'is_pinned' => false,
            'creation_datetime' => $post->creationDatetime->format(\DateTimeInterface::ATOM),
            'can_edit' => true,
            'like_count' => 0,
            'has_liked' => false,
            'comment_count' => 0,
        ]);
    }

    public function test_post_photos(): void
    {
        $this->client->disableReboot();
        $author = UserFactory::new()->asBaseUser()->create(['username' => 'julien']);

        $this->client->loginUser($author);
        $this->client->request('POST', '/api/user/profile-posts', ['content' => 'Forest National hier soir'], [
            'photos' => [$this->jpegUpload(3200, 2000, 'IMG_1.jpg'), $this->jpegUpload(800, 600, 'IMG_2.jpg')],
        ], ['CONTENT_TYPE' => 'multipart/form-data', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $post = $this->onlyPostOf($author);
        [$first, $second] = $post->images->toArray();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProfilePost',
            '@id' => '/api/user/profile-posts/' . $post->id,
            '@type' => 'ProfilePost',
            'id' => $post->id,
            'author' => [
                '@type' => 'ProfilePostAuthor',
                'username' => 'julien',
                'display_name' => 'julien',
            ],
            'content' => 'Forest National hier soir',
            'photos' => [
                ['@type' => 'ProfilePostPhoto', 'id' => $first->id, 'url' => '/api/user/profile-posts/' . $post->id . '/photos/' . $first->id],
                ['@type' => 'ProfilePostPhoto', 'id' => $second->id, 'url' => '/api/user/profile-posts/' . $post->id . '/photos/' . $second->id],
            ],
            'is_pinned' => false,
            'creation_datetime' => $post->creationDatetime->format(\DateTimeInterface::ATOM),
            'can_edit' => true,
            'like_count' => 0,
            'has_liked' => false,
            'comment_count' => 0,
        ]);

        // Stored converted, in the sender's order: WebP, 1600 px at most, the location data gone with the EXIF.
        /** @var FilesystemOperator $filesystem */
        $filesystem = self::getContainer()->get('oneup_flysystem.musicall_filesystem');
        $stored = $filesystem->read('/profile_post_images/' . $author->id . '/' . $first->storagePath);
        $this->assertSame([1600, 1000, IMAGETYPE_WEBP], array_slice((array) getimagesizefromstring($stored), 0, 3));
        $this->assertSame('IMG_1.webp', $first->originalName);
        $this->assertSame('IMG_2.webp', $second->originalName);
    }

    public function test_post_a_media_link(): void
    {
        $author = UserFactory::new()->asBaseUser()->create(['username' => 'julien']);

        $this->client->loginUser($author);
        $this->client->jsonRequest('POST', '/api/user/profile-posts', [
            'media_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $post = $this->onlyPostOf($author);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProfilePost',
            '@id' => '/api/user/profile-posts/' . $post->id,
            '@type' => 'ProfilePost',
            'id' => $post->id,
            'author' => [
                '@type' => 'ProfilePostAuthor',
                'username' => 'julien',
                'display_name' => 'julien',
            ],
            'photos' => [],
            'media' => [
                '@type' => 'ProfilePostMedia',
                'platform' => 'youtube',
                'embed_id' => 'dQw4w9WgXcQ',
                'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'title' => 'Never Gonna Give You Up',
            ],
            'is_pinned' => false,
            'creation_datetime' => $post->creationDatetime->format(\DateTimeInterface::ATOM),
            'can_edit' => true,
            'like_count' => 0,
            'has_liked' => false,
            'comment_count' => 0,
        ]);
    }

    public function test_an_empty_post_is_refused(): void
    {
        $author = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($author);
        $this->client->jsonRequest('POST', '/api/user/profile-posts', [
            'content' => '   ',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'content',
                    'message' => 'Écris un texte, ajoute des photos ou un lien',
                    'code' => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                ],
            ],
            'detail' => 'content: Écris un texte, ajoute des photos ou un lien',
            'type' => '/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            'title' => 'An error occurred',
            'description' => 'content: Écris un texte, ajoute des photos ou un lien',
        ]);
        $this->assertSame([], self::getContainer()->get(ProfilePostRepository::class)->findAll());
    }

    public function test_the_limits_are_refused(): void
    {
        $author = UserFactory::new()->asBaseUser()->create();
        $photos = array_map(fn (int $index): UploadedFile => $this->jpegUpload(100, 100, "IMG_$index.jpg"), range(1, 5));

        $this->client->loginUser($author);
        $this->client->request('POST', '/api/user/profile-posts', [
            'content' => str_repeat('a', 2001),
            'media_url' => 'https://example.com/not-a-player',
        ], ['photos' => $photos], ['CONTENT_TYPE' => 'multipart/form-data', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/0=d94b19cc-114f-4f44-9cc4-4138e80a87b9;1=183ad2de-533d-4796-a439-6d3c3852b549;2=music_all_2a3b4c5d-6e7f-8a9b-0c1d-2e3f4a5b6c7d;3=756b1212-697c-468d-a9ad-50dd783bb169',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'content',
                    'message' => 'Un post fait au plus 2000 caractères',
                    'code' => 'd94b19cc-114f-4f44-9cc4-4138e80a87b9',
                ],
                [
                    'propertyPath' => 'media_url',
                    'message' => 'Un post a des photos ou un lien, pas les deux',
                    'code' => '183ad2de-533d-4796-a439-6d3c3852b549',
                ],
                [
                    'propertyPath' => 'media_url',
                    'message' => 'URL non reconnue. Seuls YouTube, SoundCloud et Spotify sont supportés.',
                    'code' => 'music_all_2a3b4c5d-6e7f-8a9b-0c1d-2e3f4a5b6c7d',
                ],
                [
                    'propertyPath' => 'photos',
                    'message' => 'Au plus 4 photos par post',
                    'code' => '756b1212-697c-468d-a9ad-50dd783bb169',
                ],
            ],
            'detail' => "content: Un post fait au plus 2000 caractères\nmedia_url: Un post a des photos ou un lien, pas les deux\nmedia_url: URL non reconnue. Seuls YouTube, SoundCloud et Spotify sont supportés.\nphotos: Au plus 4 photos par post",
            'type' => '/validation_errors/0=d94b19cc-114f-4f44-9cc4-4138e80a87b9;1=183ad2de-533d-4796-a439-6d3c3852b549;2=music_all_2a3b4c5d-6e7f-8a9b-0c1d-2e3f4a5b6c7d;3=756b1212-697c-468d-a9ad-50dd783bb169',
            'title' => 'An error occurred',
            'description' => "content: Un post fait au plus 2000 caractères\nmedia_url: Un post a des photos ou un lien, pas les deux\nmedia_url: URL non reconnue. Seuls YouTube, SoundCloud et Spotify sont supportés.\nphotos: Au plus 4 photos par post",
        ]);
    }

    public function test_photos_and_a_link_together_are_refused(): void
    {
        $author = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($author);
        $this->client->request('POST', '/api/user/profile-posts', [
            'media_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ], ['photos' => [$this->jpegUpload(100, 100, 'IMG.jpg')]], ['CONTENT_TYPE' => 'multipart/form-data', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/183ad2de-533d-4796-a439-6d3c3852b549',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'media_url',
                    'message' => 'Un post a des photos ou un lien, pas les deux',
                    'code' => '183ad2de-533d-4796-a439-6d3c3852b549',
                ],
            ],
            'detail' => 'media_url: Un post a des photos ou un lien, pas les deux',
            'type' => '/validation_errors/183ad2de-533d-4796-a439-6d3c3852b549',
            'title' => 'An error occurred',
            'description' => 'media_url: Un post a des photos ou un lien, pas les deux',
        ]);
    }

    public function test_a_link_must_be_a_web_address(): void
    {
        $author = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($author);
        $this->client->jsonRequest('POST', '/api/user/profile-posts', [
            'media_url' => 'javascript:alert(1)//youtube.com/watch?v=dQw4w9WgXcQ',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/57c2f299-1154-4870-89bb-ef3b1f5ad229',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'media_url',
                    'message' => "Le lien n'est pas valide",
                    'code' => '57c2f299-1154-4870-89bb-ef3b1f5ad229',
                ],
            ],
            'detail' => "media_url: Le lien n'est pas valide",
            'type' => '/validation_errors/57c2f299-1154-4870-89bb-ef3b1f5ad229',
            'title' => 'An error occurred',
            'description' => "media_url: Le lien n'est pas valide",
        ]);
    }

    public function test_a_file_that_is_not_an_image_is_refused(): void
    {
        $author = UserFactory::new()->asBaseUser()->create();
        $path = $this->temporaryPath();
        file_put_contents($path, '<svg xmlns="http://www.w3.org/2000/svg"></svg>');

        $this->client->loginUser($author);
        $this->client->request('POST', '/api/user/profile-posts', [], [
            'photos' => [new UploadedFile($path, 'logo.png', 'image/png', null, true)],
        ], ['CONTENT_TYPE' => 'multipart/form-data', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/744f00bc-4389-4c74-92de-9a43cde55534',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'photos[0]',
                    'message' => "Format d'image non pris en charge (formats acceptés : JPEG, PNG, WebP, GIF)",
                    'code' => '744f00bc-4389-4c74-92de-9a43cde55534',
                ],
            ],
            'detail' => "photos[0]: Format d'image non pris en charge (formats acceptés : JPEG, PNG, WebP, GIF)",
            'type' => '/validation_errors/744f00bc-4389-4c74-92de-9a43cde55534',
            'title' => 'An error occurred',
            'description' => "photos[0]: Format d'image non pris en charge (formats acceptés : JPEG, PNG, WebP, GIF)",
        ]);
    }

    public function test_the_rate_limit(): void
    {
        $author = UserFactory::new()->asBaseUser()->create();
        self::getContainer()->get('limiter.profile_post')->create($author->getUserIdentifier())->consume(10);

        $this->client->loginUser($author);
        $this->client->jsonRequest('POST', '/api/user/profile-posts', [
            'content' => 'Encore un',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_TOO_MANY_REQUESTS);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/429',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Rate Limit Exceeded',
            'status' => 429,
            'type' => '/errors/429',
            'description' => 'Rate Limit Exceeded',
        ]);
    }

    public function test_a_visitor_cannot_post(): void
    {
        $this->client->jsonRequest('POST', '/api/user/profile-posts', [
            'content' => 'Bonjour',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    private function onlyPostOf(User $author): ProfilePost
    {
        $posts = self::getContainer()->get(ProfilePostRepository::class)->findBy(['author' => $author->id]);
        $this->assertCount(1, $posts);

        return $posts[0];
    }

    private function jpegUpload(int $width, int $height, string $clientName): UploadedFile
    {
        $path = $this->temporaryPath();
        imagejpeg(imagecreatetruecolor($width, $height), $path);

        return new UploadedFile($path, $clientName, 'image/jpeg', null, true);
    }

    private function temporaryPath(): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'profile_post_test_');
        $this->temporaryFiles[] = $path;

        return $path;
    }
}
