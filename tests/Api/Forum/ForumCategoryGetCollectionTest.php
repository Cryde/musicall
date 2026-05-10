<?php

declare(strict_types=1);

namespace App\Tests\Api\Forum;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Forum\ForumCategoryFactory;
use App\Tests\Factory\Forum\ForumFactory;
use App\Tests\Factory\Forum\ForumSourceFactory;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

#[\Zenstruck\Foundry\Attribute\ResetDatabase]
class ForumCategoryGetCollectionTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_get_collection_with_ordering(): void
    {
        $forumSource = ForumSourceFactory::new()->asRoot()->create();

        // Create categories in non-sequential order (position 3, then 1, then 2)
        $category3 = ForumCategoryFactory::new([
            'position' => 3,
            'title' => 'Third Category',
            'forumSource' => $forumSource,
        ])->create();
        $category1 = ForumCategoryFactory::new([
            'position' => 1,
            'title' => 'First Category',
            'forumSource' => $forumSource,
        ])->create();
        $category2 = ForumCategoryFactory::new([
            'position' => 2,
            'title' => 'Second Category',
            'forumSource' => $forumSource,
        ])->create();

        // Create forums in non-sequential order for category1 (position 3, 1, 2)
        $forum1c = ForumFactory::new([
            'forumCategory' => $category1,
            'position' => 3,
            'title' => 'Forum 1C',
            'slug' => 'forum-1c',
            'description' => 'Third forum in first category',
        ])->create();
        $forum1a = ForumFactory::new([
            'forumCategory' => $category1,
            'position' => 1,
            'title' => 'Forum 1A',
            'slug' => 'forum-1a',
            'description' => 'First forum in first category',
        ])->create();
        $forum1b = ForumFactory::new([
            'forumCategory' => $category1,
            'position' => 2,
            'title' => 'Forum 1B',
            'slug' => 'forum-1b',
            'description' => 'Second forum in first category',
        ])->create();

        // Create forums in non-sequential order for category2 (position 2, 1)
        $forum2b = ForumFactory::new([
            'forumCategory' => $category2,
            'position' => 2,
            'title' => 'Forum 2B',
            'slug' => 'forum-2b',
            'description' => 'Second forum in second category',
        ])->create();
        $forum2a = ForumFactory::new([
            'forumCategory' => $category2,
            'position' => 1,
            'title' => 'Forum 2A',
            'slug' => 'forum-2a',
            'description' => 'First forum in second category',
        ])->create();

        // Create single forum for category3
        $forum3a = ForumFactory::new([
            'forumCategory' => $category3,
            'position' => 1,
            'title' => 'Forum 3A',
            'slug' => 'forum-3a',
            'description' => 'Only forum in third category',
        ])->create();

        $this->client->request('GET', '/api/forums/categories');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ForumCategory',
            '@id' => '/api/forums/categories',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/forum_categories/' . $category1->id,
                    '@type' => 'ForumCategory',
                    'id' => $category1->id,
                    'title' => 'First Category',
                    'forums' => [
                        [
                            '@type' => 'Forum',
                            'id' => $forum1a->id,
                            'title' => 'Forum 1A',
                            'slug' => 'forum-1a',
                            'description' => 'First forum in first category',
                        ],
                        [
                            '@type' => 'Forum',
                            'id' => $forum1b->id,
                            'title' => 'Forum 1B',
                            'slug' => 'forum-1b',
                            'description' => 'Second forum in first category',
                        ],
                        [
                            '@type' => 'Forum',
                            'id' => $forum1c->id,
                            'title' => 'Forum 1C',
                            'slug' => 'forum-1c',
                            'description' => 'Third forum in first category',
                        ],
                    ],
                ],
                [
                    '@id' => '/api/forum_categories/' . $category2->id,
                    '@type' => 'ForumCategory',
                    'id' => $category2->id,
                    'title' => 'Second Category',
                    'forums' => [
                        [
                            '@type' => 'Forum',
                            'id' => $forum2a->id,
                            'title' => 'Forum 2A',
                            'slug' => 'forum-2a',
                            'description' => 'First forum in second category',
                        ],
                        [
                            '@type' => 'Forum',
                            'id' => $forum2b->id,
                            'title' => 'Forum 2B',
                            'slug' => 'forum-2b',
                            'description' => 'Second forum in second category',
                        ],
                    ],
                ],
                [
                    '@id' => '/api/forum_categories/' . $category3->id,
                    '@type' => 'ForumCategory',
                    'id' => $category3->id,
                    'title' => 'Third Category',
                    'forums' => [
                        [
                            '@type' => 'Forum',
                            'id' => $forum3a->id,
                            'title' => 'Forum 3A',
                            'slug' => 'forum-3a',
                            'description' => 'Only forum in third category',
                        ],
                    ],
                ],
            ],
            'totalItems' => 3,
        ]);
    }

    public function test_get_collection_filters_by_root_source(): void
    {
        $rootSource = ForumSourceFactory::new()->asRoot()->create();
        $otherSource = ForumSourceFactory::new(['slug' => 'other'])->create();

        // Create category for root source
        $rootCategory = ForumCategoryFactory::new([
            'position' => 1,
            'title' => 'Root Category',
            'forumSource' => $rootSource,
        ])->create();
        $rootForum = ForumFactory::new([
            'forumCategory' => $rootCategory,
            'position' => 1,
            'title' => 'Root Forum',
            'slug' => 'root-forum',
            'description' => 'Forum in root source',
        ])->create();

        // Create category for other source (should NOT appear)
        $otherCategory = ForumCategoryFactory::new([
            'position' => 1,
            'title' => 'Other Category',
            'forumSource' => $otherSource,
        ])->create();
        ForumFactory::new([
            'forumCategory' => $otherCategory,
            'position' => 1,
            'title' => 'Other Forum',
            'slug' => 'other-forum',
            'description' => 'Forum in other source',
        ])->create();

        $this->client->request('GET', '/api/forums/categories');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ForumCategory',
            '@id' => '/api/forums/categories',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/forum_categories/' . $rootCategory->id,
                    '@type' => 'ForumCategory',
                    'id' => $rootCategory->id,
                    'title' => 'Root Category',
                    'forums' => [
                        [
                            '@type' => 'Forum',
                            'id' => $rootForum->id,
                            'title' => 'Root Forum',
                            'slug' => 'root-forum',
                            'description' => 'Forum in root source',
                        ],
                    ],
                ],
            ],
            'totalItems' => 1,
        ]);
    }

    public function test_get_collection_empty_when_no_categories(): void
    {
        ForumSourceFactory::new()->asRoot()->create();

        $this->client->request('GET', '/api/forums/categories');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ForumCategory',
            '@id' => '/api/forums/categories',
            '@type' => 'Collection',
            'member' => [],
            'totalItems' => 0,
        ]);
    }
}
