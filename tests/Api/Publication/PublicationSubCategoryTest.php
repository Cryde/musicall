<?php

declare(strict_types=1);

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
            '@id'      => '/api/publication_sub_categories/' . $sub->getId(),
            '@type'    => 'PublicationSubCategory',
        ]); // empty for now (no data)
    }
}
