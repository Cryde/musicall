<?php

namespace App\Tests\Api\Publication;

use App\Entity\Publication;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Metric\ViewCacheFactory;
use App\Tests\Factory\Publication\PublicationFactory;
use App\Tests\Factory\Publication\PublicationSubCategoryFactory;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class PublicationGetCollectionTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_get_publications(): void
    {
        $sub = PublicationSubCategoryFactory::new()->asChronique()->create();
        $sub2 = PublicationSubCategoryFactory::new()->asNews()->create();
        $author = UserFactory::new()->asAdminUser()->create();

        $pub1 = PublicationFactory::new([
            'author'              => $author,
            'content'             => 'publication_content1',
            'creationDatetime'    => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2020-01-02T02:03:04+00:00'),
            'editionDatetime'     => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2021-01-02T02:03:04+00:00'),
            'publicationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2022-01-02T02:03:04+00:00'),
            'shortDescription'    => 'Petite description de la publication 1',
            'slug'                => 'titre-de-la-publication-1',
            'status'              => Publication::STATUS_ONLINE,
            'subCategory'         => $sub,
            'title'               => 'Titre de la publication 1',
            'type'                => Publication::TYPE_TEXT,
            'viewCache'           => ViewCacheFactory::new(['count' => 10])->create(),
        ])->create()->_real();

        $pub2 = PublicationFactory::new([
            'author'              => $author,
            'content'             => 'publication_content2',
            'creationDatetime'    => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2020-01-02T02:03:04+00:00'),
            'editionDatetime'     => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2021-01-02T02:03:04+00:00'),
            'publicationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2000-01-02T02:03:04+00:00'),
            'shortDescription'    => 'Petite description de la publication 2',
            'slug'                => 'titre-de-la-publication-2',
            'status'              => Publication::STATUS_ONLINE,
            'subCategory'         => $sub,
            'title'               => 'Titre de la publication 2',
            'type'                => Publication::TYPE_TEXT,
            'viewCache'           => ViewCacheFactory::new(['count' => 20])->create(),
        ])->create()->_real();

        // not taken (status) :
        PublicationFactory::new([
            'author' => $author, 'status' => Publication::STATUS_DRAFT, 'subCategory' => $sub,
        ])->create();
        // not taken (status):
        PublicationFactory::new([
            'author' => $author, 'status' => Publication::STATUS_PENDING, 'subCategory' => $sub,
        ])->create();
        // not taken (category):
        PublicationFactory::new([
            'author' => $author, 'status' => Publication::STATUS_ONLINE, 'subCategory' => $sub2,
        ])->create();

        $this->client->request('GET', '/api/publications', [
            'order' => ['publication_datetime' => 'asc'],
            'sub_category.slug' => 'chroniques'
        ]);
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context'         => '/api/contexts/Publication',
            '@id'              => '/api/publications',
            '@type'            => 'Collection',
            'member'     => [
                [
                    '@id'                  => '/api/publications/titre-de-la-publication-2',
                    '@type'                => 'Publication',
                    'id'                   => $pub2->getId(),
                    'title'                => 'Titre de la publication 2',
                    'sub_category'         => [
                        '@id' => '/api/publication_sub_categories/' . $sub->_real()->getId(),
                        '@type' => 'PublicationSubCategory',
                        'id'         => $sub->_real()->getId(),
                        'title'      => 'Chroniques',
                        'slug'       => 'chroniques',
                        'type_label' => 'publication',
                        'is_course'  => false,
                    ],
                    'author'               => [
                        '@id' => '/api/users/' . $author->_real()->getId(),
                        '@type' => 'User',
                        'username' => 'user_admin',
                    ],
                    'slug'                 => 'titre-de-la-publication-2',
                    'publication_datetime' => '2000-01-02T02:03:04+00:00',
                    'cover'                => null,
                    'type_label'           => 'text',
                    'description'          => 'Petite description de la publication 2',
                ],
                [
                    '@id'                  => '/api/publications/titre-de-la-publication-1',
                    '@type'                => 'Publication',
                    'id'                   => $pub1->getId(),
                    'title'                => 'Titre de la publication 1',
                    'sub_category'         => [
                        '@id' => '/api/publication_sub_categories/' . $sub->_real()->getId(),
                        '@type' => 'PublicationSubCategory',
                        'id'         => $sub->_real()->getId(),
                        'title'      => 'Chroniques',
                        'slug'       => 'chroniques',
                        'type_label' => 'publication',
                        'is_course'  => false,
                    ],
                    'author'               => [
                        '@id' => '/api/users/' . $author->_real()->getId(),
                        '@type' => 'User',
                        'username' => 'user_admin',
                    ],
                    'slug'                 => 'titre-de-la-publication-1',
                    'publication_datetime' => '2022-01-02T02:03:04+00:00',
                    'cover'                => null,
                    'type_label'           => 'text',
                    'description'          => 'Petite description de la publication 1',
                ],
            ],
            'totalItems' => 2,
            'view'       => [
                '@id'   => '/api/publications?order%5Bpublication_datetime%5D=asc&sub_category.slug=chroniques',
                '@type' => 'PartialCollectionView',
            ],
            'search'     => [
                '@type'                        => 'IriTemplate',
                'template'               => '/api/publications{?order[publication_datetime],sub_category.slug,sub_category.slug[],sub_category.type,sub_category.type[]}',
                'variableRepresentation' => 'BasicRepresentation',
                'mapping'                => [
                    [
                        '@type'    => 'IriTemplateMapping',
                        'variable' => 'order[publication_datetime]',
                        'property' => 'publication_datetime',
                        'required' => false,
                    ],
                    [
                        '@type'    => 'IriTemplateMapping',
                        'variable' => 'sub_category.slug',
                        'property' => 'sub_category.slug',
                        'required' => false,
                    ],
                    [
                        '@type'    => 'IriTemplateMapping',
                        'variable' => 'sub_category.slug[]',
                        'property' => 'sub_category.slug',
                        'required' => false,
                    ],
                    [
                        '@type' => 'IriTemplateMapping',
                        'variable' => 'sub_category.type',
                        'property' => 'sub_category.type',
                        'required' => false,
                    ],
                    [
                        '@type' => 'IriTemplateMapping',
                        'variable' => 'sub_category.type[]',
                        'property' => 'sub_category.type',
                        'required' => false,
                    ],
                ],
            ],
        ]);
    }
}