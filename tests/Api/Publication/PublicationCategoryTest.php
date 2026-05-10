<?php

declare(strict_types=1);

namespace App\Tests\Api\Publication;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Publication\PublicationSubCategoryFactory;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

#[\Zenstruck\Foundry\Attribute\ResetDatabase]
class PublicationCategoryTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_get_publication_categories(): void
    {
        $sub1 = PublicationSubCategoryFactory::new()->asNews()->create();
        $sub2 = PublicationSubCategoryFactory::new()->asChronique()->create();
        $sub3 = PublicationSubCategoryFactory::new()->asInterview()->create();

        // Course category - should not be returned
        PublicationSubCategoryFactory::new()->asCourse()->create();

        $this->client->request('GET', '/api/publication-categories');
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertJsonEquals([
            '@context'   => '/api/contexts/PublicationCategory',
            '@id'        => '/api/publication-categories',
            '@type'      => 'Collection',
            'member'     => [
                [
                    '@id'      => '/api/publication_categories/' . $sub1->id,
                    '@type'    => 'PublicationCategory',
                    'id'       => $sub1->id,
                    'title'    => 'News',
                    'slug'     => 'news',
                    'position' => 1,
                ],
                [
                    '@id'      => '/api/publication_categories/' . $sub2->id,
                    '@type'    => 'PublicationCategory',
                    'id'       => $sub2->id,
                    'title'    => 'Chroniques',
                    'slug'     => 'chroniques',
                    'position' => 2,
                ],
                [
                    '@id'      => '/api/publication_categories/' . $sub3->id,
                    '@type'    => 'PublicationCategory',
                    'id'       => $sub3->id,
                    'title'    => 'Interviews',
                    'slug'     => 'interviews',
                    'position' => 3,
                ],
            ],
            'totalItems' => 3,
        ]);
    }
}
