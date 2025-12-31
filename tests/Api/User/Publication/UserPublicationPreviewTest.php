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
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class UserPublicationPreviewTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_preview_not_logged(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $category = PublicationSubCategoryFactory::new()->asNews()->create();
        $publication = PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $category,
            'status' => Publication::STATUS_DRAFT,
        ]);

        $this->client->request('GET', '/api/user/publications/' . $publication->getId() . '/preview');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function test_preview_draft_success(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'testauthor']);
        $category = PublicationSubCategoryFactory::new()->asNews()->create();
        $publication = PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $category,
            'title' => 'Test Draft Publication',
            'slug' => 'test-draft-publication',
            'shortDescription' => 'Short desc',
            'content' => '<p>Test content</p>',
            'status' => Publication::STATUS_DRAFT,
            'type' => Publication::TYPE_TEXT,
        ]);

        $this->client->loginUser($user->_real());
        $this->client->request('GET', '/api/user/publications/' . $publication->getId() . '/preview');
        $this->assertResponseIsSuccessful();

        $this->assertJsonContains([
            '@type' => 'UserPublicationPreview',
            'id' => $publication->getId(),
            'title' => 'Test Draft Publication',
            'slug' => 'test-draft-publication',
            'short_description' => 'Short desc',
            'content' => '<p>Test content</p>',
            'status_id' => Publication::STATUS_DRAFT,
            'status_label' => 'Brouillon',
            'category' => [
                '@type' => 'UserPublicationCategory',
                'id' => $category->getId(),
                'title' => 'News',
            ],
            'author' => [
                '@type' => 'UserPublicationPreviewAuthor',
                'username' => 'testauthor',
            ],
        ]);
    }

    public function test_preview_pending_success(): void
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => 'pendingauthor']);
        $category = PublicationSubCategoryFactory::new()->asNews()->create();
        $publication = PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $category,
            'title' => 'Test Pending Publication',
            'slug' => 'test-pending-publication',
            'shortDescription' => 'Pending short desc',
            'content' => '<p>Pending content</p>',
            'status' => Publication::STATUS_PENDING,
            'type' => Publication::TYPE_TEXT,
        ]);

        $this->client->loginUser($user->_real());
        $this->client->request('GET', '/api/user/publications/' . $publication->getId() . '/preview');
        $this->assertResponseIsSuccessful();

        $this->assertJsonContains([
            '@type' => 'UserPublicationPreview',
            'id' => $publication->getId(),
            'title' => 'Test Pending Publication',
            'status_id' => Publication::STATUS_PENDING,
            'status_label' => 'En validation',
            'author' => [
                '@type' => 'UserPublicationPreviewAuthor',
                'username' => 'pendingauthor',
            ],
        ]);
    }

    public function test_preview_not_owner(): void
    {
        $owner = UserFactory::new()->asBaseUser()->create(['username' => 'owner', 'email' => 'owner@test.com']);
        $otherUser = UserFactory::new()->asBaseUser()->create(['username' => 'other', 'email' => 'other@test.com']);
        $category = PublicationSubCategoryFactory::new()->asNews()->create();
        $publication = PublicationFactory::new()->create([
            'author' => $owner,
            'subCategory' => $category,
            'status' => Publication::STATUS_DRAFT,
        ]);

        $this->client->loginUser($otherUser->_real());
        $this->client->request('GET', '/api/user/publications/' . $publication->getId() . '/preview');
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_preview_online_publication_forbidden(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $category = PublicationSubCategoryFactory::new()->asNews()->create();
        $publication = PublicationFactory::new()->create([
            'author' => $user,
            'subCategory' => $category,
            'status' => Publication::STATUS_ONLINE,
        ]);

        $this->client->loginUser($user->_real());
        $this->client->request('GET', '/api/user/publications/' . $publication->getId() . '/preview');
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_preview_not_found(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user->_real());
        $this->client->request('GET', '/api/user/publications/999999/preview');
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

}
