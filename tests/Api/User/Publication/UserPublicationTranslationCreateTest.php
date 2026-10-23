<?php

declare(strict_types=1);

namespace App\Tests\Api\User\Publication;

use App\Entity\Publication;
use App\Repository\PublicationRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Publication\PublicationFactory;
use App\Tests\Factory\Publication\PublicationSubCategoryFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class UserPublicationTranslationCreateTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_not_logged(): void
    {
        $publication = PublicationFactory::new()->create(['type' => Publication::TYPE_TEXT]);

        $this->client->jsonRequest('POST', '/api/user/publications/' . $publication->id . '/translations', [
            'title' => 'My article',
            'language' => 'en',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    public function test_translate_an_online_publication(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $category = PublicationSubCategoryFactory::new()->asNews()->create();
        $original = PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $category,
            'title' => 'Mon article',
            'slug' => 'mon-article',
            'status' => Publication::STATUS_ONLINE,
            'type' => Publication::TYPE_TEXT,
            'language' => 'fr',
        ]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/publications/' . $original->id . '/translations', [
            'title' => 'My article',
            'language' => 'en',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $translation = self::getContainer()->get(PublicationRepository::class)->findOneBy(['language' => 'en']);
        $this->assertInstanceOf(Publication::class, $translation);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserPublicationEdit',
            '@id' => '/api/user/publications/' . $translation->id . '/edit',
            '@type' => 'UserPublicationEdit',
            'id' => $translation->id,
            'title' => 'My article',
            'slug' => 'my-article-',
            'status_id' => Publication::STATUS_DRAFT,
            'category' => [
                '@type' => 'UserPublicationCategory',
                'id' => $category->id,
                'title' => 'News',
                'slug' => 'news',
            ],
            'tags' => [],
            'language' => 'en',
            'translation_languages' => ['fr'],
        ]);
        $this->assertSame($user->id, $translation->author->id);
        $this->assertSame(Publication::TYPE_TEXT, $translation->type);
        $this->assertNotNull($translation->translationGroup);
        $reloadedOriginal = self::getContainer()->get(PublicationRepository::class)->find($original->id);
        $this->assertSame($translation->translationGroup, $reloadedOriginal->translationGroup);
        $this->assertSame(Publication::STATUS_ONLINE, $reloadedOriginal->status);
    }

    public function test_a_translation_keeping_the_title_of_a_draft_gets_its_own_slug(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $category = PublicationSubCategoryFactory::new()->asNews()->create();
        $original = PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $category,
            'title' => 'Metallica',
            'slug' => 'metallica-',
            'status' => Publication::STATUS_DRAFT,
            'type' => Publication::TYPE_TEXT,
            'language' => 'fr',
        ]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/publications/' . $original->id . '/translations', [
            'title' => 'Metallica',
            'language' => 'en',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $translation = self::getContainer()->get(PublicationRepository::class)->findOneBy(['language' => 'en']);
        $this->assertInstanceOf(Publication::class, $translation);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserPublicationEdit',
            '@id' => '/api/user/publications/' . $translation->id . '/edit',
            '@type' => 'UserPublicationEdit',
            'id' => $translation->id,
            'title' => 'Metallica',
            'slug' => 'metallica-1-',
            'status_id' => Publication::STATUS_DRAFT,
            'category' => [
                '@type' => 'UserPublicationCategory',
                'id' => $category->id,
                'title' => 'News',
                'slug' => 'news',
            ],
            'tags' => [],
            'language' => 'en',
            'translation_languages' => ['fr'],
        ]);
    }

    /** Someone else's publication would take the translation into its group if the body chose it. */
    public function test_the_body_cannot_choose_the_publication_translated(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $category = PublicationSubCategoryFactory::new()->asNews()->create();
        $someoneElse = PublicationFactory::new()->create([
            'status' => Publication::STATUS_ONLINE,
            'type' => Publication::TYPE_TEXT,
            'language' => 'fr',
        ]);
        $own = PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $category,
            'status' => Publication::STATUS_ONLINE,
            'type' => Publication::TYPE_TEXT,
            'language' => 'fr',
        ]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/publications/' . $own->id . '/translations', [
            'id' => $someoneElse->id,
            'title' => 'My article',
            'language' => 'en',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $repository = self::getContainer()->get(PublicationRepository::class);
        $translation = $repository->findOneBy(['language' => 'en']);
        $this->assertInstanceOf(Publication::class, $translation);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserPublicationEdit',
            '@id' => '/api/user/publications/' . $translation->id . '/edit',
            '@type' => 'UserPublicationEdit',
            'id' => $translation->id,
            'title' => 'My article',
            'slug' => 'my-article-',
            'status_id' => Publication::STATUS_DRAFT,
            'category' => [
                '@type' => 'UserPublicationCategory',
                'id' => $category->id,
                'title' => 'News',
                'slug' => 'news',
            ],
            'tags' => [],
            'language' => 'en',
            'translation_languages' => ['fr'],
        ]);
        $this->assertNotNull($translation->translationGroup);
        $this->assertSame($translation->translationGroup, $repository->find($own->id)->translationGroup);
        $this->assertNull($repository->find($someoneElse->id)->translationGroup);
    }

    public function test_the_body_cannot_free_a_taken_language(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $own = PublicationFactory::new()->create([
            'author' => $user,
            'status' => Publication::STATUS_ONLINE,
            'type' => Publication::TYPE_TEXT,
            'language' => 'fr',
        ]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/publications/' . $own->id . '/translations', [
            'title' => 'Mon article',
            'language' => 'fr',
            'taken_languages' => [],
            'takenLanguages' => [],
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/6b3befbc-2f01-4ddf-be21-b57898905284',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'language',
                    'message' => 'Cette publication existe déjà dans cette langue',
                    'code' => '6b3befbc-2f01-4ddf-be21-b57898905284',
                ],
            ],
            'detail' => 'language: Cette publication existe déjà dans cette langue',
            'type' => '/validation_errors/6b3befbc-2f01-4ddf-be21-b57898905284',
            'title' => 'An error occurred',
            'description' => 'language: Cette publication existe déjà dans cette langue',
        ]);
        $this->assertSame(1, self::getContainer()->get(PublicationRepository::class)->count([]));
    }

    public function test_refuses_the_language_of_the_publication_itself(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $original = PublicationFactory::new()->create([
            'author' => $user,
            'status' => Publication::STATUS_ONLINE,
            'type' => Publication::TYPE_TEXT,
            'language' => 'fr',
        ]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/publications/' . $original->id . '/translations', [
            'title' => 'Mon article',
            'language' => 'fr',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/6b3befbc-2f01-4ddf-be21-b57898905284',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'language',
                    'message' => 'Cette publication existe déjà dans cette langue',
                    'code' => '6b3befbc-2f01-4ddf-be21-b57898905284',
                ],
            ],
            'detail' => 'language: Cette publication existe déjà dans cette langue',
            'type' => '/validation_errors/6b3befbc-2f01-4ddf-be21-b57898905284',
            'title' => 'An error occurred',
            'description' => 'language: Cette publication existe déjà dans cette langue',
        ]);
        $this->assertSame(1, self::getContainer()->get(PublicationRepository::class)->count([]));
    }

    /** The case the unique index would otherwise answer with a 500: neither the original nor itself has it. */
    public function test_refuses_the_language_of_another_translation(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $original = PublicationFactory::new()->create([
            'author' => $user,
            'status' => Publication::STATUS_ONLINE,
            'type' => Publication::TYPE_TEXT,
            'language' => 'fr',
        ]);
        $english = PublicationFactory::new()->create([
            'author' => $user,
            'status' => Publication::STATUS_DRAFT,
            'type' => Publication::TYPE_TEXT,
            'language' => 'en',
        ]);
        $german = PublicationFactory::new()->create([
            'author' => $user,
            'status' => Publication::STATUS_DRAFT,
            'type' => Publication::TYPE_TEXT,
            'language' => 'de',
        ]);
        $english->joinTranslationGroupOf($original);
        $german->joinTranslationGroupOf($original);
        self::getContainer()->get('doctrine')->getManager()->flush();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/publications/' . $german->id . '/translations', [
            'title' => 'My article',
            'language' => 'en',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/6b3befbc-2f01-4ddf-be21-b57898905284',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'language',
                    'message' => 'Cette publication existe déjà dans cette langue',
                    'code' => '6b3befbc-2f01-4ddf-be21-b57898905284',
                ],
            ],
            'detail' => 'language: Cette publication existe déjà dans cette langue',
            'type' => '/validation_errors/6b3befbc-2f01-4ddf-be21-b57898905284',
            'title' => 'An error occurred',
            'description' => 'language: Cette publication existe déjà dans cette langue',
        ]);
        $this->assertSame(3, self::getContainer()->get(PublicationRepository::class)->count([]));
    }

    public function test_refuses_a_language_that_is_not_enabled(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $original = PublicationFactory::new()->create([
            'author' => $user,
            'status' => Publication::STATUS_ONLINE,
            'type' => Publication::TYPE_TEXT,
            'language' => 'fr',
        ]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/publications/' . $original->id . '/translations', [
            'title' => 'Mein Artikel',
            'language' => 'de',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/music_all_a16a08ba-d862-4666-a61c-3aa93707c199',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'language',
                    'message' => 'Cette langue n\'est pas disponible',
                    'code' => 'music_all_a16a08ba-d862-4666-a61c-3aa93707c199',
                ],
            ],
            'detail' => 'language: Cette langue n\'est pas disponible',
            'type' => '/validation_errors/music_all_a16a08ba-d862-4666-a61c-3aa93707c199',
            'title' => 'An error occurred',
            'description' => 'language: Cette langue n\'est pas disponible',
        ]);
        $this->assertSame(1, self::getContainer()->get(PublicationRepository::class)->count([]));
    }

    public function test_requires_a_language_and_a_title(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $original = PublicationFactory::new()->create([
            'author' => $user,
            'status' => Publication::STATUS_ONLINE,
            'type' => Publication::TYPE_TEXT,
            'language' => 'fr',
        ]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/publications/' . $original->id . '/translations', [
            'title' => 'ab',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/0=9ff3fdc4-b214-49db-8718-39c315e33d45;1=c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'title',
                    'message' => 'Le titre doit contenir au moins 3 caractères',
                    'code' => '9ff3fdc4-b214-49db-8718-39c315e33d45',
                ],
                [
                    'propertyPath' => 'language',
                    'message' => 'Choisis une langue',
                    'code' => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                ],
            ],
            'detail' => "title: Le titre doit contenir au moins 3 caractères\nlanguage: Choisis une langue",
            'type' => '/validation_errors/0=9ff3fdc4-b214-49db-8718-39c315e33d45;1=c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            'title' => 'An error occurred',
            'description' => "title: Le titre doit contenir au moins 3 caractères\nlanguage: Choisis une langue",
        ]);
    }

    public function test_only_the_author_translates(): void
    {
        $author = UserFactory::new()->asBaseUser()->create();
        $other = UserFactory::new()->asAdminUser()->create();
        $original = PublicationFactory::new()->create([
            'author' => $author,
            'status' => Publication::STATUS_ONLINE,
            'type' => Publication::TYPE_TEXT,
            'language' => 'fr',
        ]);

        $this->client->loginUser($other);
        $this->client->jsonRequest('POST', '/api/user/publications/' . $original->id . '/translations', [
            'title' => 'My article',
            'language' => 'en',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'You are not the owner of this publication',
            'status' => 403,
            'type' => '/errors/403',
            'description' => 'You are not the owner of this publication',
        ]);
        $this->assertSame(1, self::getContainer()->get(PublicationRepository::class)->count([]));
    }

    public function test_an_unknown_publication(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/publications/999999/translations', [
            'title' => 'My article',
            'language' => 'en',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Publication not found',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Publication not found',
        ]);
    }

    public function test_a_video_is_never_translated(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $video = PublicationFactory::new()->create([
            'author' => $user,
            'status' => Publication::STATUS_ONLINE,
            'type' => Publication::TYPE_VIDEO,
        ]);

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/user/publications/' . $video->id . '/translations', [
            'title' => 'My video',
            'language' => 'en',
        ], ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/422',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Une vidéo est montrée dans toutes les langues, elle ne se traduit pas',
            'status' => 422,
            'type' => '/errors/422',
            'description' => 'Une vidéo est montrée dans toutes les langues, elle ne se traduit pas',
        ]);
        $this->assertSame(1, self::getContainer()->get(PublicationRepository::class)->count([]));
    }
}
