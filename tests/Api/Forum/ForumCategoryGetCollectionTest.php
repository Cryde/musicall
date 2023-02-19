<?php

namespace App\Tests\Api\Forum;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Forum\ForumCategoryFactory;
use App\Tests\Factory\Forum\ForumFactory;
use App\Tests\Factory\Forum\ForumSourceFactory;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class ForumCategoryGetCollectionTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_get_collection()
    {
        $forumSource = ForumSourceFactory::new()->asRoot()->create();
        $forumSource2 = ForumSourceFactory::new(['slug' => 'private'])->create();
        $forumCategory1 = ForumCategoryFactory::new(['position' => 2, 'title' => 'Forum 1 category title', 'forumSource' => $forumSource])->create();
        $forum1 = ForumFactory::new(['forumCategory' => $forumCategory1, 'position' => 20])->create();
        $forum2 = ForumFactory::new(['forumCategory' => $forumCategory1, 'position' => 30])->create();
        $forum3 = ForumFactory::new(['forumCategory' => $forumCategory1, 'position' => 10])->create();
        $forumCategory2 = ForumCategoryFactory::new(['position' => 1, 'title' => 'Forum 2 category title', 'forumSource' => $forumSource])->create();
        $forum4 = ForumFactory::new(['forumCategory' => $forumCategory2, 'position' => 2])->create();
        $forum5 = ForumFactory::new(['forumCategory' => $forumCategory2, 'position' => 1])->create();
        $forumCategory3 = ForumCategoryFactory::new(['position' => 3, 'title' => 'Forum 3 category title', 'forumSource' => $forumSource])->create();
        // should appear in the result :
        $forumCategory4 = ForumCategoryFactory::new(['position' => 1, 'title' => 'Forum 4 category title', 'forumSource' => $forumSource2])->create();

        $this->client->request('GET', '/api/forum_categories?forumSource.slug=root&order[position]=asc&order[forums.position]=asc');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context'         => '/api/contexts/ForumCategory',
            '@id'              => '/api/forum_categories',
            '@type'            => 'hydra:Collection',
            'hydra:member'     => [
                [
                    'id'     => $forumCategory2->getId(),
                    'title'  => 'Forum 2 category title',
                    'forums' => [
                        [
                            'id'          => $forum5->getId(),
                            'title'       => $forum5->getTitle(),
                            'slug'        => $forum5->getSlug(),
                            'description' => $forum5->getDescription(),
                        ],
                        [
                            'id'          => $forum4->getId(),
                            'title'       => $forum4->getTitle(),
                            'slug'        => $forum4->getSlug(),
                            'description' => $forum4->getDescription(),
                        ],
                    ],
                ],
                [
                    'id'     => $forumCategory1->getId(),
                    'title'  => 'Forum 1 category title',
                    'forums' => [
                        [
                            'id'          => $forum3->getId(),
                            'title'       => $forum3->getTitle(),
                            'slug'        => $forum3->getSlug(),
                            'description' => $forum3->getDescription(),
                        ],
                        [
                            'id'          => $forum1->getId(),
                            'title'       => $forum1->getTitle(),
                            'slug'        => $forum1->getSlug(),
                            'description' => $forum1->getDescription(),
                        ],
                        [
                            'id'          => $forum2->getId(),
                            'title'       => $forum2->getTitle(),
                            'slug'        => $forum2->getSlug(),
                            'description' => $forum2->getDescription(),
                        ],
                    ],
                ],
                [
                    'id'     => $forumCategory3->getId(),
                    'title'  => 'Forum 3 category title',
                    'forums' => [],
                ],
            ],
            'hydra:totalItems' => 3,
            'hydra:view'       => [
                '@id'   => '/api/forum_categories?forumSource.slug=root&order%5Bposition%5D=asc&order%5Bforums.position%5D=asc',
                '@type' => 'hydra:PartialCollectionView',
            ],
            'hydra:search'     => [
                '@type'                        => 'hydra:IriTemplate',
                'hydra:template'               => '/api/forum_categories{?order[position],order[forums.position],forum_source.slug,forum_source.slug[]}',
                'hydra:variableRepresentation' => 'BasicRepresentation',
                'hydra:mapping'                => [
                    [
                        '@type'    => 'IriTemplateMapping',
                        'variable' => 'order[position]',
                        'property' => 'position',
                        'required' => false,
                    ],
                    [
                        '@type'    => 'IriTemplateMapping',
                        'variable' => 'order[forums.position]',
                        'property' => 'forums.position',
                        'required' => false,
                    ],
                    [
                        '@type'    => 'IriTemplateMapping',
                        'variable' => 'forum_source.slug',
                        'property' => 'forum_source.slug',
                        'required' => false,
                    ],
                    [
                        '@type'    => 'IriTemplateMapping',
                        'variable' => 'forum_source.slug[]',
                        'property' => 'forum_source.slug',
                        'required' => false,
                    ],
                ],
            ],
        ]);
    }
}