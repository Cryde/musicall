<?php

declare(strict_types=1);

namespace App\Tests\Api\Forum;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Forum\ForumCategoryFactory;
use App\Tests\Factory\Forum\ForumFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class ForumGetTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_get_item(): void
    {
        $forumCategory = ForumCategoryFactory::new(['position' => 1, 'title' => 'Forum category title'])->create();
        $forum = ForumFactory::new([
            'description' => 'Forum description',
            'forumCategory' => $forumCategory,
            'position' => 5,
            'postNumber' => 10,
            'slug' => 'forum-title',
            'title' => 'Forum title',
            'topicNumber' => 20,
            'updateDatetime' => null,
        ])->create();

        $this->client->request('GET', '/api/forum/forum-title');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Forum',
            '@id' => '/api/forum/forum-title',
            '@type' => 'Forum',
            'id' => $forum->getId(),
            'title' => 'Forum title',
            'forum_category' => [
                '@type' => 'ForumCategory',
                'id' => $forumCategory->getId(),
                'title' => 'Forum category title',
            ],
        ]);
    }

    public function test_get_item_not_found(): void
    {
        $this->client->request('GET', '/api/forum/not-found');
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Forum not found',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Forum not found',
        ]);
    }
}
