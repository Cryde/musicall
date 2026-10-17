<?php

declare(strict_types=1);

namespace App\Tests\Api\Attribute;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Attribute\StyleFactory;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class StyleGetCollectionTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_get_styles_sorted_by_name(): void
    {
        $rock = StyleFactory::new()->asRock()->create();
        $jazz = StyleFactory::new()->asJazz()->create();

        $this->client->request('GET', '/api/styles');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Style',
            '@id' => '/api/styles',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/styles/' . $jazz->id,
                    '@type' => 'Style',
                    'id' => $jazz->id,
                    'name' => 'Jazz',
                    'slug' => 'jazz',
                ],
                [
                    '@id' => '/api/styles/' . $rock->id,
                    '@type' => 'Style',
                    'id' => $rock->id,
                    'name' => 'Rock',
                    'slug' => 'rock',
                ],
            ],
            'totalItems' => 2,
        ]);
    }
}
