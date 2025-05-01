<?php

namespace App\Tests\Integration\Controller;

use App\Entity\PublicationSubCategory;
use App\Tests\Factory\Publication\PublicationFactory;
use App\Tests\Factory\Publication\PublicationSubCategoryFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class HomeControllerTest extends WebTestCase
{
    use ResetDatabase, Factories;

    public function test_old_publication_redirect(): void
    {
        $client = static::createClient();
        $client->request('GET', '/publication/my-super-slug');

        $this->assertResponseStatusCodeSame(Response::HTTP_MOVED_PERMANENTLY);
        $this->assertResponseRedirects('http://localhost/publications/my-super-slug');
    }

    public function test_old_course_redirect(): void
    {
        $client = static::createClient();

        PublicationFactory::createOne([
            'content' => 'test',
            'oldPublicationId' => 42,
            'slug' => 'old-slug-we-care',
            'subCategory' => PublicationSubCategoryFactory::createOne(['type' => PublicationSubCategory::TYPE_COURSE,])
        ]);

        $client->request('GET', '/cours/42/we-dont-care');

        $this->assertResponseStatusCodeSame(Response::HTTP_MOVED_PERMANENTLY);
        $this->assertResponseRedirects('http://localhost/cours/old-slug-we-care');
    }
}