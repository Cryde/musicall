<?php

namespace App\Tests\Api\Publication;

use App\Entity\Publication;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Metric\ViewCacheFactory;
use App\Tests\Factory\Publication\PublicationFactory;
use App\Tests\Factory\Publication\PublicationSubCategoryFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class PublicationGetTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_get_item_publication(): void
    {
        $sub = PublicationSubCategoryFactory::new()->asChronique()->create();
        $author = UserFactory::new()->asAdminUser()->create();
        $viewCache = ViewCacheFactory::new(['count' => 123])->create();
        PublicationFactory::new([
            'author'              => $author,
            'content'             => 'publication_content',
            'creationDatetime'    => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2020-01-02T02:03:04+00:00'),
            'editionDatetime'     => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2021-01-02T02:03:04+00:00'),
            'publicationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2022-01-02T02:03:04+00:00'),
            'shortDescription'    => 'Petite description de la publication',
            'slug'                => 'titre-de-la-publication',
            'status'              => Publication::STATUS_ONLINE,
            'subCategory'         => $sub,
            'title'               => 'Titre de la publication',
            'type'                => Publication::TYPE_TEXT,
            'viewCache'           => $viewCache,
        ])->create();

        $this->client->request('GET', '/api/publications/titre-de-la-publication');
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context'             => '/api/contexts/Publication',
            '@id'                  => '/api/publications/titre-de-la-publication',
            '@type'                => 'Publication',
            'title'                => 'Titre de la publication',
            'sub_category'         => [
                'id'    => $sub->object()->getId(),
                'title' => 'Chroniques',
                'slug'  => 'chroniques',
            ],
            'author'               => [
                'username' => 'user_admin',
            ],
            'slug'                 => 'titre-de-la-publication',
            'short_description'    => 'Petite description de la publication',
            'content'              => 'publication_content',
            'publication_datetime' => '2022-01-02T02:03:04+00:00',
            'type_label'           => 'text',
        ]);
    }

    public function test_get_item_publication_not_found(): void
    {
        $this->client->request('GET', '/api/publications/not_found');
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'Publication inexistante',
            'title'             => 'An error occurred',
            'detail'            => 'Publication inexistante',
            'status'            => 404,
            'type'              => '/errors/404',
        ]);
    }
}