<?php

declare(strict_types=1);

namespace App\Tests\Api\User\Publication;

use App\Entity\Publication;
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
        $this->assertSame('Brouillon', $response['status_label']);
        $this->assertSame($category->id, $response['category']['id']);
        $this->assertSame('News', $response['category']['title']);
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
