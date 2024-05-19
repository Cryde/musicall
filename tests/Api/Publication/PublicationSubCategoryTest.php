<?php

namespace App\Tests\Api\Publication;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Publication\PublicationSubCategoryFactory;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class PublicationSubCategoryTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_get_item_publication_sub_category(): void
    {
        $sub = PublicationSubCategoryFactory::new()->asDecouvertes()->create();

        $this->client->request('GET', '/api/publication_sub_categories/' . $sub->getId());
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/PublicationSubCategory',
            '@id' => '/api/publication_sub_categories/' . $sub->getId(),
            '@type' => 'PublicationSubCategory',
        ]); // empty for now (no data)
    }

    public function test_get_publication_sub_category(): void
    {
        $sub1 = PublicationSubCategoryFactory::new()->asNews()->create();
        $sub2 = PublicationSubCategoryFactory::new()->asChronique()->create();
        $sub3 = PublicationSubCategoryFactory::new()->asLiveReports()->create();
        $sub4 = PublicationSubCategoryFactory::new()->asArticle()->create();
        $sub5 = PublicationSubCategoryFactory::new()->asDecouvertes()->create();
        $sub6 = PublicationSubCategoryFactory::new()->asInterview()->create();

        $this->client->request('GET', '/api/publication_sub_categories?order[position]=asc');
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context'         => '/api/contexts/PublicationSubCategory',
            '@id'              => '/api/publication_sub_categories',
            '@type'            => 'hydra:Collection',
            'hydra:member'     => [
                [
                    '@id' => '/api/publication_sub_categories/' . $sub1->getId(),
                    '@type' => 'PublicationSubCategory',
                    'id'       => $sub1->getId(),
                    'title'    => 'News',
                    'slug'     => 'news',
                    'position' => 1,
                    'type'     => 1,
                ],
                [
                    '@id' => '/api/publication_sub_categories/' . $sub2->getId(),
                    '@type' => 'PublicationSubCategory',
                    'id'       => $sub2->getId(),
                    'title'    => 'Chroniques',
                    'slug'     => 'chroniques',
                    'position' => 2,
                    'type'     => 1,
                ],
                [
                    '@id' => '/api/publication_sub_categories/' . $sub6->getId(),
                    '@type' => 'PublicationSubCategory',
                    'id'       => $sub6->getId(),
                    'title'    => 'Interviews',
                    'slug'     => 'interviews',
                    'position' => 3,
                    'type'     => 1,
                ],
                [
                    '@id' => '/api/publication_sub_categories/' . $sub3->getId(),
                    '@type' => 'PublicationSubCategory',
                    'id'       => $sub3->getId(),
                    'title'    => 'Live-reports',
                    'slug'     => 'live-reports',
                    'position' => 4,
                    'type'     => 1,
                ],
                [
                    '@id' => '/api/publication_sub_categories/' . $sub4->getId(),
                    '@type' => 'PublicationSubCategory',
                    'id'       => $sub4->getId(),
                    'title'    => 'Articles',
                    'slug'     => 'articles',
                    'position' => 5,
                    'type'     => 1,
                ],
                [
                    '@id' => '/api/publication_sub_categories/' . $sub5->getId(),
                    '@type' => 'PublicationSubCategory',
                    'id'       => $sub5->getId(),
                    'title'    => 'Découvertes',
                    'slug'     => 'decouvertes',
                    'position' => 6,
                    'type'     => 1,
                ],
            ],
            'hydra:totalItems' => 6,
            'hydra:search'     => [
                '@type'                        => 'hydra:IriTemplate',
                'hydra:template'               => '/api/publication_sub_categories{?order[position]}',
                'hydra:variableRepresentation' => 'BasicRepresentation',
                'hydra:mapping'                => [
                    [
                        '@type'    => 'IriTemplateMapping',
                        'variable' => 'order[position]',
                        'property' => 'position',
                        'required' => false,
                    ],
                ],
            ],
            'hydra:view'       => [
                '@id'   => '/api/publication_sub_categories?order%5Bposition%5D=asc',
                '@type' => 'hydra:PartialCollectionView',
            ],
        ]);
    }
}
