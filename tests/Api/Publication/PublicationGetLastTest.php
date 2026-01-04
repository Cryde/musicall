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

class PublicationGetLastTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_get_last_publications(): void
    {
        $sub = PublicationSubCategoryFactory::new()->asChronique()->create();
        $author = UserFactory::new()->asAdminUser()->create();

        // Create 6 publications, only 4 should be returned
        $pub1 = PublicationFactory::new([
            'author'              => $author,
            'publicationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2020-01-01T00:00:00+00:00'),
            'shortDescription'    => 'Description 1',
            'slug'                => 'publication-1',
            'status'              => Publication::STATUS_ONLINE,
            'subCategory'         => $sub,
            'title'               => 'Publication 1',
            'type'                => Publication::TYPE_TEXT,
            'viewCache'           => ViewCacheFactory::new(['count' => 10])->create(),
        ])->create()->_real();

        $pub2 = PublicationFactory::new([
            'author'              => $author,
            'publicationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2021-01-01T00:00:00+00:00'),
            'shortDescription'    => 'Description 2',
            'slug'                => 'publication-2',
            'status'              => Publication::STATUS_ONLINE,
            'subCategory'         => $sub,
            'title'               => 'Publication 2',
            'type'                => Publication::TYPE_TEXT,
            'viewCache'           => ViewCacheFactory::new(['count' => 20])->create(),
        ])->create()->_real();

        $pub3 = PublicationFactory::new([
            'author'              => $author,
            'publicationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2022-01-01T00:00:00+00:00'),
            'shortDescription'    => 'Description 3',
            'slug'                => 'publication-3',
            'status'              => Publication::STATUS_ONLINE,
            'subCategory'         => $sub,
            'title'               => 'Publication 3',
            'type'                => Publication::TYPE_TEXT,
            'viewCache'           => ViewCacheFactory::new(['count' => 30])->create(),
        ])->create()->_real();

        $pub4 = PublicationFactory::new([
            'author'              => $author,
            'publicationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2023-01-01T00:00:00+00:00'),
            'shortDescription'    => 'Description 4',
            'slug'                => 'publication-4',
            'status'              => Publication::STATUS_ONLINE,
            'subCategory'         => $sub,
            'title'               => 'Publication 4',
            'type'                => Publication::TYPE_TEXT,
            'viewCache'           => ViewCacheFactory::new(['count' => 40])->create(),
        ])->create()->_real();

        $pub5 = PublicationFactory::new([
            'author'              => $author,
            'publicationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2024-01-01T00:00:00+00:00'),
            'shortDescription'    => 'Description 5',
            'slug'                => 'publication-5',
            'status'              => Publication::STATUS_ONLINE,
            'subCategory'         => $sub,
            'title'               => 'Publication 5',
            'type'                => Publication::TYPE_TEXT,
            'viewCache'           => ViewCacheFactory::new(['count' => 50])->create(),
        ])->create()->_real();

        // This one should not appear (not online)
        PublicationFactory::new([
            'author'     => $author,
            'status'     => Publication::STATUS_DRAFT,
            'subCategory' => $sub,
        ])->create();

        $this->client->request('GET', '/api/last-publications');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context'   => '/api/contexts/LastPublication',
            '@id'        => '/api/last-publications',
            '@type'      => 'Collection',
            'member'     => [
                [
                    '@id'                  => '/api/publications/publication-5',
                    '@type'                => 'Publication',
                    'id'                   => $pub5->getId(),
                    'title'                => 'Publication 5',
                    'sub_category'         => [
                        '@id'        => '/api/publication_sub_categories/' . $sub->_real()->getId(),
                        '@type'      => 'PublicationSubCategory',
                        'id'         => $sub->_real()->getId(),
                        'title'      => 'Chroniques',
                        'slug'       => 'chroniques',
                        'type_label' => 'publication',
                        'is_course'  => false,
                    ],
                    'author'               => [
                        '@id'      => '/api/users/' . $author->_real()->getId(),
                        '@type'    => 'User',
                        'username' => 'user_admin',
                    ],
                    'slug'                 => 'publication-5',
                    'publication_datetime' => '2024-01-01T00:00:00+00:00',
                    'cover'                => null,
                    'type_label'           => 'text',
                    'description'          => 'Description 5',
                ],
                [
                    '@id'                  => '/api/publications/publication-4',
                    '@type'                => 'Publication',
                    'id'                   => $pub4->getId(),
                    'title'                => 'Publication 4',
                    'sub_category'         => [
                        '@id'        => '/api/publication_sub_categories/' . $sub->_real()->getId(),
                        '@type'      => 'PublicationSubCategory',
                        'id'         => $sub->_real()->getId(),
                        'title'      => 'Chroniques',
                        'slug'       => 'chroniques',
                        'type_label' => 'publication',
                        'is_course'  => false,
                    ],
                    'author'               => [
                        '@id'      => '/api/users/' . $author->_real()->getId(),
                        '@type'    => 'User',
                        'username' => 'user_admin',
                    ],
                    'slug'                 => 'publication-4',
                    'publication_datetime' => '2023-01-01T00:00:00+00:00',
                    'cover'                => null,
                    'type_label'           => 'text',
                    'description'          => 'Description 4',
                ],
                [
                    '@id'                  => '/api/publications/publication-3',
                    '@type'                => 'Publication',
                    'id'                   => $pub3->getId(),
                    'title'                => 'Publication 3',
                    'sub_category'         => [
                        '@id'        => '/api/publication_sub_categories/' . $sub->_real()->getId(),
                        '@type'      => 'PublicationSubCategory',
                        'id'         => $sub->_real()->getId(),
                        'title'      => 'Chroniques',
                        'slug'       => 'chroniques',
                        'type_label' => 'publication',
                        'is_course'  => false,
                    ],
                    'author'               => [
                        '@id'      => '/api/users/' . $author->_real()->getId(),
                        '@type'    => 'User',
                        'username' => 'user_admin',
                    ],
                    'slug'                 => 'publication-3',
                    'publication_datetime' => '2022-01-01T00:00:00+00:00',
                    'cover'                => null,
                    'type_label'           => 'text',
                    'description'          => 'Description 3',
                ],
                [
                    '@id'                  => '/api/publications/publication-2',
                    '@type'                => 'Publication',
                    'id'                   => $pub2->getId(),
                    'title'                => 'Publication 2',
                    'sub_category'         => [
                        '@id'        => '/api/publication_sub_categories/' . $sub->_real()->getId(),
                        '@type'      => 'PublicationSubCategory',
                        'id'         => $sub->_real()->getId(),
                        'title'      => 'Chroniques',
                        'slug'       => 'chroniques',
                        'type_label' => 'publication',
                        'is_course'  => false,
                    ],
                    'author'               => [
                        '@id'      => '/api/users/' . $author->_real()->getId(),
                        '@type'    => 'User',
                        'username' => 'user_admin',
                    ],
                    'slug'                 => 'publication-2',
                    'publication_datetime' => '2021-01-01T00:00:00+00:00',
                    'cover'                => null,
                    'type_label'           => 'text',
                    'description'          => 'Description 2',
                ],
            ],
            'totalItems' => 4,
        ]);
    }
}
