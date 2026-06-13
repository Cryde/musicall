<?php

declare(strict_types=1);

namespace App\Tests\Api\Metric;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Metric\ViewCacheFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class ViewCacheNotExposedTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_view_cache_item_is_not_a_public_api_resource(): void
    {
        // Seed a real ViewCache: if the GET operation still existed it would 200
        // for this id. It must be 404 (the entity is no longer an API resource).
        $viewCache = ViewCacheFactory::createOne();

        $this->client->request('GET', '/api/view_caches/' . $viewCache->id);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }
}
