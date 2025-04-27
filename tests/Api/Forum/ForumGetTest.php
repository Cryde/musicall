<?php

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

    public function test_get_item()
    {
        $forumCategory = ForumCategoryFactory::new(['position' => 1, 'title' => 'forum category title'])->create();
        $forum = ForumFactory::new([
            'description'    => 'Forum description',
            'forumCategory'  => $forumCategory,
            'position'       => 5,
            'postNumber'     => 10,
            'slug'           => 'forum-title',
            'title'          => 'Forum title',
            'topicNumber'    => 20,
            'updateDatetime' => null,
        ])->create();

        $this->client->request('GET', '/api/forums/forum-title');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Forum',
            '@id' => '/api/forums/forum-title',
            '@type' => 'Forum',
            'id'             => $forum->getId(),
            'title'          => 'Forum title',
            'forum_category' => [
                'id'    => $forumCategory->getId(),
                'title' => 'forum category title',
                '@id' => '/api/forum_categories/' . $forumCategory->getId(),
                '@type' => 'ForumCategory'
            ],
        ]);
    }

    public function test_get_item_not_found()
    {
        $this->client->request('GET', '/api/forums/not-found');
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title'       => 'An error occurred',
            'description' => 'Not Found',
            'detail'            => 'Not Found',
            'status'            => 404,
            'type'              => '/errors/404',
            '@context' => '/api/contexts/Error',
        ]);
    }
}