<?php

declare(strict_types=1);

namespace App\Tests\Api\Publication;

use App\Entity\Publication;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Publication\PublicationFactory;
use App\Tests\Factory\Publication\PublicationSubCategoryFactory;
use App\Tests\Factory\Publication\TagFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\HttpFoundation\Response;

#[\Zenstruck\Foundry\Attribute\ResetDatabase]
class PopularTagTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_get_popular_tags_orders_by_publication_count_desc(): void
    {
        $sub = PublicationSubCategoryFactory::new()->asChronique()->create();
        $author = UserFactory::new()->asAdminUser()->create();

        $metal = TagFactory::new(['label' => 'Metal', 'slug' => 'metal'])->create();
        $jazz = TagFactory::new(['label' => 'Jazz', 'slug' => 'jazz'])->create();
        $blues = TagFactory::new(['label' => 'Blues', 'slug' => 'blues'])->create();
        // Unused tag — must NOT appear (zero publications)
        TagFactory::new(['label' => 'Unused', 'slug' => 'unused'])->create();

        // 3 publications tagged Metal
        for ($i = 1; $i <= 3; $i++) {
            PublicationFactory::new([
                'author'              => $author,
                'slug'                => 'metal-' . $i,
                'status'              => Publication::STATUS_ONLINE,
                'subCategory'         => $sub,
                'title'               => 'Metal ' . $i,
                'type'                => Publication::TYPE_TEXT,
                'tags'                => new ArrayCollection([$metal]),
            ])->create();
        }

        // 2 publications tagged Jazz
        for ($i = 1; $i <= 2; $i++) {
            PublicationFactory::new([
                'author'              => $author,
                'slug'                => 'jazz-' . $i,
                'status'              => Publication::STATUS_ONLINE,
                'subCategory'         => $sub,
                'title'               => 'Jazz ' . $i,
                'type'                => Publication::TYPE_TEXT,
                'tags'                => new ArrayCollection([$jazz]),
            ])->create();
        }

        // 1 publication tagged Blues
        PublicationFactory::new([
            'author'              => $author,
            'slug'                => 'blues-1',
            'status'              => Publication::STATUS_ONLINE,
            'subCategory'         => $sub,
            'title'               => 'Blues 1',
            'type'                => Publication::TYPE_TEXT,
            'tags'                => new ArrayCollection([$blues]),
        ])->create();

        $this->client->request('GET', '/api/tags/popular');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context'   => '/api/contexts/PopularTag',
            '@id'        => '/api/tags/popular',
            '@type'      => 'Collection',
            'member'     => [
                ['@id' => '/api/popular_tags/metal', '@type' => 'PopularTag', 'slug' => 'metal', 'label' => 'Metal', 'publication_count' => 3],
                ['@id' => '/api/popular_tags/jazz',  '@type' => 'PopularTag', 'slug' => 'jazz',  'label' => 'Jazz',  'publication_count' => 2],
                ['@id' => '/api/popular_tags/blues', '@type' => 'PopularTag', 'slug' => 'blues', 'label' => 'Blues', 'publication_count' => 1],
            ],
            'totalItems' => 3,
        ]);
    }

    public function test_get_popular_tags_respects_count_query_param(): void
    {
        $sub = PublicationSubCategoryFactory::new()->asChronique()->create();
        $author = UserFactory::new()->asAdminUser()->create();

        $a = TagFactory::new(['label' => 'A', 'slug' => 'a'])->create();
        $b = TagFactory::new(['label' => 'B', 'slug' => 'b'])->create();
        $c = TagFactory::new(['label' => 'C', 'slug' => 'c'])->create();

        foreach (['a' => $a, 'b' => $b, 'c' => $c] as $slug => $tag) {
            PublicationFactory::new([
                'author'              => $author,
                'slug'                => 'pub-' . $slug,
                'status'              => Publication::STATUS_ONLINE,
                'subCategory'         => $sub,
                'title'               => 'Pub ' . $slug,
                'type'                => Publication::TYPE_TEXT,
                'tags'                => new ArrayCollection([$tag]),
            ])->create();
        }

        $this->client->request('GET', '/api/tags/popular?count=2');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context'   => '/api/contexts/PopularTag',
            '@id'        => '/api/tags/popular',
            '@type'      => 'Collection',
            'member'     => [
                ['@id' => '/api/popular_tags/a', '@type' => 'PopularTag', 'slug' => 'a', 'label' => 'A', 'publication_count' => 1],
                ['@id' => '/api/popular_tags/b', '@type' => 'PopularTag', 'slug' => 'b', 'label' => 'B', 'publication_count' => 1],
            ],
            'totalItems' => 2,
            'view'       => [
                '@id'   => '/api/tags/popular?count=2',
                '@type' => 'PartialCollectionView',
            ],
        ]);
    }

    public function test_get_popular_tags_empty(): void
    {
        $this->client->request('GET', '/api/tags/popular');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context'   => '/api/contexts/PopularTag',
            '@id'        => '/api/tags/popular',
            '@type'      => 'Collection',
            'member'     => [],
            'totalItems' => 0,
        ]);
    }

    public function test_get_popular_tags_rejects_count_above_max(): void
    {
        $this->client->request('GET', '/api/tags/popular?count=51');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id'      => '/api/validation_errors/04b91c99-a946-4221-afc5-e65ebac401eb',
            '@type'    => 'ConstraintViolation',
            'status'   => 422,
            'violations' => [
                [
                    'propertyPath' => 'count',
                    'message'      => 'Cette valeur doit être comprise entre 1 et 50.',
                    'code'         => '04b91c99-a946-4221-afc5-e65ebac401eb',
                ],
            ],
            'detail'      => 'count: Cette valeur doit être comprise entre 1 et 50.',
            'description' => 'count: Cette valeur doit être comprise entre 1 et 50.',
            'type'        => '/validation_errors/04b91c99-a946-4221-afc5-e65ebac401eb',
            'title'       => 'An error occurred',
        ]);
    }
}
