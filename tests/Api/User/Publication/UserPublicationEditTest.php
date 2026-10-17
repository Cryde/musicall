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
class UserPublicationEditTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_get_edit_not_logged(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $category = PublicationSubCategoryFactory::new()->asNews()->create();
        $publication = PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $category,
            'status' => Publication::STATUS_DRAFT,
        ]);

        $this->client->request('GET', '/api/user/publications/' . $publication->id . '/edit');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function test_get_edit_success(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $category = PublicationSubCategoryFactory::new()->asNews()->create();
        $publication = PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $category,
            'title' => 'Test Publication',
            'slug' => 'test-publication',
            'shortDescription' => 'Short desc',
            'content' => '<p>Test content</p>',
            'status' => Publication::STATUS_DRAFT,
            'type' => Publication::TYPE_TEXT,
        ]);

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/user/publications/' . $publication->id . '/edit');
        $this->assertResponseIsSuccessful();

        $response = $this->getResponseAsArray();
        $this->assertSame('UserPublicationEdit', $response['@type']);
        $this->assertSame($publication->id, $response['id']);
        $this->assertSame('Test Publication', $response['title']);
        $this->assertSame('test-publication', $response['slug']);
        $this->assertSame('Short desc', $response['short_description']);
        $this->assertSame('<p>Test content</p>', $response['content']);
        $this->assertSame(Publication::STATUS_DRAFT, $response['status_id']);
        $this->assertSame($category->id, $response['category']['id']);
        $this->assertSame('News', $response['category']['title']);
        $this->assertSame('fr', $response['language']);
    }

    public function test_get_edit_not_owner(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create(['username' => 'owner', 'email' => 'owner@test.com']);
        $otherUser = UserFactory::new()->asBaseUser()->create(['username' => 'other', 'email' => 'other@test.com']);
        $category = PublicationSubCategoryFactory::new()->asNews()->create();
        $publication = PublicationFactory::new()->create([
            'author' => $owner,
            'subCategory' => $category,
            'status' => Publication::STATUS_DRAFT,
        ]);

        $this->client->loginUser($otherUser);
        $this->client->request('GET', '/api/user/publications/' . $publication->id . '/edit');
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_get_edit_not_draft(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $category = PublicationSubCategoryFactory::new()->asNews()->create();
        $publication = PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $category,
            'status' => Publication::STATUS_ONLINE,
        ]);

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/user/publications/' . $publication->id . '/edit');
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_get_edit_not_found(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/user/publications/999999/edit');
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function test_patch_not_logged(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $category = PublicationSubCategoryFactory::new()->asNews()->create();
        $publication = PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $category,
            'status' => Publication::STATUS_DRAFT,
        ]);

        $this->client->request('PATCH', '/api/user/publications/' . $publication->id, [], [], [
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function test_patch_success(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $category = PublicationSubCategoryFactory::new()->asNews()->create();
        $publication = PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $category,
            'title' => 'Original Title',
            'shortDescription' => 'Original desc',
            'content' => '<p>Original content</p>',
            'status' => Publication::STATUS_DRAFT,
            'type' => Publication::TYPE_TEXT,
        ]);

        $this->client->loginUser($user);
        $this->client->request('PATCH', '/api/user/publications/' . $publication->id, [], [], [
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ], json_encode([
            'title' => 'Updated Title',
            'shortDescription' => 'Updated desc',
            'content' => '<p>Updated content</p>',
        ]));

        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertSame('UserPublicationEdit', $response['@type']);
        $this->assertSame($publication->id, $response['id']);
        $this->assertSame('Updated Title', $response['title']);
        $this->assertSame('Updated desc', $response['short_description']);
        $this->assertSame('<p>Updated content</p>', $response['content']);
        $this->assertSame(Publication::STATUS_DRAFT, $response['status_id']);
    }

    public function test_get_edit_names_the_languages_of_its_translations(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $category = PublicationSubCategoryFactory::new()->asNews()->create();
        $original = PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $category,
            'slug' => 'the-original',
            'status' => Publication::STATUS_ONLINE,
            'type' => Publication::TYPE_TEXT,
            'language' => 'en',
        ]);
        $translation = PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $category,
            'title' => 'La traduction',
            'slug' => 'la-traduction-',
            'shortDescription' => null,
            'content' => null,
            'status' => Publication::STATUS_DRAFT,
            'type' => Publication::TYPE_TEXT,
            'language' => 'fr',
        ]);
        $translation->joinTranslationGroupOf($original);
        self::getContainer()->get('doctrine')->getManager()->flush();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/user/publications/' . $translation->id . '/edit');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserPublicationEdit',
            '@id' => '/api/user/publications/' . $translation->id . '/edit',
            '@type' => 'UserPublicationEdit',
            'id' => $translation->id,
            'title' => 'La traduction',
            'slug' => 'la-traduction-',
            'status_id' => Publication::STATUS_DRAFT,
            'category' => [
                '@type' => 'UserPublicationCategory',
                'id' => $category->id,
                'title' => 'News',
                'slug' => 'news',
            ],
            'tags' => [],
            'language' => 'fr',
            'translation_languages' => ['en'],
        ]);
    }

    public function test_patch_changes_the_language(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $category = PublicationSubCategoryFactory::new()->asNews()->create();
        $publication = PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $category,
            'title' => 'Mon article',
            'slug' => 'mon-article',
            'shortDescription' => 'Une description',
            'content' => 'Le contenu',
            'status' => Publication::STATUS_DRAFT,
            'type' => Publication::TYPE_TEXT,
        ]);

        $this->client->loginUser($user);
        $this->client->request('PATCH', '/api/user/publications/' . $publication->id, [], [], [
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ], json_encode(['language' => 'en']));

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/UserPublicationEdit',
            '@id' => '/api/user/publications/' . $publication->id,
            '@type' => 'UserPublicationEdit',
            'id' => $publication->id,
            'title' => 'Mon article',
            'slug' => 'mon-article',
            'short_description' => 'Une description',
            'content' => 'Le contenu',
            'status_id' => Publication::STATUS_DRAFT,
            'category' => [
                '@type' => 'UserPublicationCategory',
                'id' => $category->id,
                'title' => 'News',
                'slug' => 'news',
            ],
            'tags' => [],
            'language' => 'en',
            'translation_languages' => [],
        ]);
        $reloaded = self::getContainer()->get(PublicationRepository::class)->find($publication->id);
        $this->assertSame('en', $reloaded->language);
    }

    public function test_patch_refuses_a_language_that_is_not_enabled(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $publication = PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => PublicationSubCategoryFactory::new()->asNews(),
            'status' => Publication::STATUS_DRAFT,
            'type' => Publication::TYPE_TEXT,
        ]);

        $this->client->loginUser($user);
        $this->client->request('PATCH', '/api/user/publications/' . $publication->id, [], [], [
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ], json_encode(['language' => 'de']));

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
        $reloaded = self::getContainer()->get(PublicationRepository::class)->find($publication->id);
        $this->assertSame('fr', $reloaded->language);
    }

    public function test_patch_refuses_to_remove_the_language_of_a_text(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $publication = PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => PublicationSubCategoryFactory::new()->asNews(),
            'status' => Publication::STATUS_DRAFT,
            'type' => Publication::TYPE_TEXT,
        ]);

        $this->client->loginUser($user);
        $this->client->request('PATCH', '/api/user/publications/' . $publication->id, [], [], [
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ], json_encode(['language' => null]));

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
        $reloaded = self::getContainer()->get(PublicationRepository::class)->find($publication->id);
        $this->assertSame('fr', $reloaded->language);
    }

    public function test_patch_refuses_the_language_of_another_translation(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $category = PublicationSubCategoryFactory::new()->asNews()->create();
        $original = PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $category,
            'status' => Publication::STATUS_ONLINE,
            'type' => Publication::TYPE_TEXT,
            'language' => 'en',
        ]);
        $translation = PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $category,
            'status' => Publication::STATUS_DRAFT,
            'type' => Publication::TYPE_TEXT,
            'language' => 'fr',
        ]);
        $translation->joinTranslationGroupOf($original);
        self::getContainer()->get('doctrine')->getManager()->flush();

        $this->client->loginUser($user);
        // The languages taken are the server's: sending none does not free one.
        $this->client->request('PATCH', '/api/user/publications/' . $translation->id, [], [], [
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ], json_encode(['language' => 'en', 'translation_languages' => [], 'translationLanguages' => []]));

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
        $reloaded = self::getContainer()->get(PublicationRepository::class)->find($translation->id);
        $this->assertSame('fr', $reloaded->language);
    }

    public function test_patch_partial_update(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $category = PublicationSubCategoryFactory::new()->asNews()->create();
        $publication = PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $category,
            'title' => 'Original Title',
            'shortDescription' => 'Original desc',
            'content' => '<p>Original content</p>',
            'status' => Publication::STATUS_DRAFT,
            'type' => Publication::TYPE_TEXT,
        ]);

        $this->client->loginUser($user);
        $this->client->request('PATCH', '/api/user/publications/' . $publication->id, [], [], [
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ], json_encode([
            'content' => '<p>Only content updated</p>',
        ]));

        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertSame('Original Title', $response['title']);
        $this->assertSame('Original desc', $response['short_description']);
        $this->assertSame('<p>Only content updated</p>', $response['content']);
    }

    public function test_patch_not_owner(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create(['username' => 'owner', 'email' => 'owner@test.com']);
        $otherUser = UserFactory::new()->asBaseUser()->create(['username' => 'other', 'email' => 'other@test.com']);
        $category = PublicationSubCategoryFactory::new()->asNews()->create();
        $publication = PublicationFactory::new()->create([
            'author' => $owner,
            'subCategory' => $category,
            'status' => Publication::STATUS_DRAFT,
        ]);

        $this->client->loginUser($otherUser);
        $this->client->request('PATCH', '/api/user/publications/' . $publication->id, [], [], [
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ], json_encode([
            'title' => 'Hacked Title',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_patch_not_draft(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $category = PublicationSubCategoryFactory::new()->asNews()->create();
        $publication = PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $category,
            'status' => Publication::STATUS_ONLINE,
        ]);

        $this->client->loginUser($user);
        $this->client->request('PATCH', '/api/user/publications/' . $publication->id, [], [], [
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ], json_encode([
            'title' => 'Cannot Edit Published',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
