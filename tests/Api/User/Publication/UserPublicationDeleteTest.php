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
class UserPublicationDeleteTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_not_logged(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $category = PublicationSubCategoryFactory::new()->asNews()->create();

        $publication = PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $category,
            'title' => 'Publication to delete',
            'slug' => 'publication-to-delete',
            'status' => Publication::STATUS_DRAFT,
            'type' => Publication::TYPE_TEXT,
        ]);

        $this->client->request('DELETE', '/api/user/publications/' . $publication->id);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code' => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function test_delete_own_draft_publication(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $category = PublicationSubCategoryFactory::new()->asNews()->create();

        $publication = PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $category,
            'title' => 'Draft Publication',
            'slug' => 'draft-publication',
            'status' => Publication::STATUS_DRAFT,
            'type' => Publication::TYPE_TEXT,
        ]);

        $publicationId = $publication->id;

        $this->client->loginUser($user);
        $this->client->request('DELETE', '/api/user/publications/' . $publicationId);
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    public function test_cannot_delete_other_user_publication(): void
    {
        $user1 = UserFactory::new()->asBaseUser()->create(['username' => 'user1', 'email' => 'user1@test.com']);
        $user2 = UserFactory::new()->asBaseUser()->create(['username' => 'user2', 'email' => 'user2@test.com']);
        $category = PublicationSubCategoryFactory::new()->asNews()->create();

        $publication = PublicationFactory::new()->create([
            'author' => $user1,
            'subCategory' => $category,
            'title' => 'User 1 Publication',
            'slug' => 'user-1-publication',
            'status' => Publication::STATUS_DRAFT,
            'type' => Publication::TYPE_TEXT,
        ]);

        $this->client->loginUser($user2);
        $this->client->request('DELETE', '/api/user/publications/' . $publication->id);
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
    }

    public function test_cannot_delete_online_publication(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $category = PublicationSubCategoryFactory::new()->asNews()->create();

        $publication = PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $category,
            'title' => 'Online Publication',
            'slug' => 'online-publication',
            'status' => Publication::STATUS_ONLINE,
            'type' => Publication::TYPE_TEXT,
        ]);

        $this->client->loginUser($user);
        $this->client->request('DELETE', '/api/user/publications/' . $publication->id);
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'You can only delete draft publications',
            'status' => 403,
            'type' => '/errors/403',
            'description' => 'You can only delete draft publications',
        ]);
    }

    public function test_cannot_delete_pending_publication(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $category = PublicationSubCategoryFactory::new()->asNews()->create();

        $publication = PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $category,
            'title' => 'Pending Publication',
            'slug' => 'pending-publication',
            'status' => Publication::STATUS_PENDING,
            'type' => Publication::TYPE_TEXT,
        ]);

        $this->client->loginUser($user);
        $this->client->request('DELETE', '/api/user/publications/' . $publication->id);
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'You can only delete draft publications',
            'status' => 403,
            'type' => '/errors/403',
            'description' => 'You can only delete draft publications',
        ]);
    }

    public function test_delete_not_found_publication(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user);
        $this->client->request('DELETE', '/api/user/publications/999999');
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
}
