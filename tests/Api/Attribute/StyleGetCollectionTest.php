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

    public function test_get_styles_in_french_keeps_the_database_order(): void
    {
        $rock = StyleFactory::new()->asRock()->create();
        $jazz = StyleFactory::new()->asJazz()->create();
        $alternativeRock = StyleFactory::new()->create(['name' => 'Rock Alternatif', 'slug' => 'rock-alternatif']);

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
                [
                    '@id' => '/api/styles/' . $alternativeRock->id,
                    '@type' => 'Style',
                    'id' => $alternativeRock->id,
                    'name' => 'Rock Alternatif',
                    'slug' => 'rock-alternatif',
                ],
            ],
            'totalItems' => 3,
        ]);
    }

    /** « Rock Alternatif » sorts after « Rock », but Alternative Rock sorts first. */
    public function test_get_styles_in_english_sorted_by_the_english_name(): void
    {
        $rock = StyleFactory::new()->asRock()->create();
        $jazz = StyleFactory::new()->asJazz()->create();
        $alternativeRock = StyleFactory::new()->create(['name' => 'Rock Alternatif', 'slug' => 'rock-alternatif']);

        $this->client->request('GET', '/api/styles', server: ['HTTP_ACCEPT_LANGUAGE' => 'en']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Style',
            '@id' => '/api/styles',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/styles/' . $alternativeRock->id,
                    '@type' => 'Style',
                    'id' => $alternativeRock->id,
                    'name' => 'Alternative Rock',
                    'slug' => 'rock-alternatif',
                ],
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
            'totalItems' => 3,
        ]);
    }
}
