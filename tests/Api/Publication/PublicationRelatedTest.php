<?php

declare(strict_types=1);

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

class PublicationRelatedTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_get_related_publications(): void
    {
        $subChronique = PublicationSubCategoryFactory::new()->asChronique()->create();
        $subNews = PublicationSubCategoryFactory::new()->asNews()->create();
        $author = UserFactory::new()->asAdminUser()->create();

        // Current publication we're viewing
        $currentPub = PublicationFactory::new([
            'author'              => $author,
            'publicationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2024-01-01T00:00:00+00:00'),
            'shortDescription'    => 'Current publication',
            'slug'                => 'current-publication',
            'status'              => Publication::STATUS_ONLINE,
            'subCategory'         => $subChronique,
            'title'               => 'Current Publication',
            'type'                => Publication::TYPE_TEXT,
            'viewCache'           => ViewCacheFactory::new(['count' => 10])->create(),
        ])->create()->_real();

        // Related publications (same subcategory)
        $related1 = PublicationFactory::new([
            'author'              => $author,
            'publicationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2023-01-01T00:00:00+00:00'),
            'shortDescription'    => 'Related 1',
            'slug'                => 'related-1',
            'status'              => Publication::STATUS_ONLINE,
            'subCategory'         => $subChronique,
            'title'               => 'Related Publication 1',
            'type'                => Publication::TYPE_TEXT,
            'viewCache'           => ViewCacheFactory::new(['count' => 20])->create(),
        ])->create()->_real();

        $related2 = PublicationFactory::new([
            'author'              => $author,
            'publicationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2022-01-01T00:00:00+00:00'),
            'shortDescription'    => 'Related 2',
            'slug'                => 'related-2',
            'status'              => Publication::STATUS_ONLINE,
            'subCategory'         => $subChronique,
            'title'               => 'Related Publication 2',
            'type'                => Publication::TYPE_TEXT,
            'viewCache'           => ViewCacheFactory::new(['count' => 30])->create(),
        ])->create()->_real();

        // Publication in different subcategory (should NOT appear)
        PublicationFactory::new([
            'author'              => $author,
            'publicationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2023-06-01T00:00:00+00:00'),
            'shortDescription'    => 'Different category',
            'slug'                => 'different-category',
            'status'              => Publication::STATUS_ONLINE,
            'subCategory'         => $subNews,
            'title'               => 'Different Category Publication',
            'type'                => Publication::TYPE_TEXT,
            'viewCache'           => ViewCacheFactory::new(['count' => 40])->create(),
        ])->create();

        // Draft publication (should NOT appear)
        PublicationFactory::new([
            'author'     => $author,
            'slug'       => 'draft-publication',
            'status'     => Publication::STATUS_DRAFT,
            'subCategory' => $subChronique,
        ])->create();

        $this->client->request('GET', '/api/publications/current-publication/related');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context'   => '/api/contexts/RelatedPublication',
            '@id'        => '/api/publications/current-publication/related',
            '@type'      => 'Collection',
            'member'     => [
                [
                    '@id'                  => '/api/publications/related-1',
                    '@type'                => 'Publication',
                    'id'                   => $related1->getId(),
                    'title'                => 'Related Publication 1',
                    'sub_category'         => [
                        '@id'        => '/api/publication_sub_categories/' . $subChronique->_real()->getId(),
                        '@type'      => 'PublicationSubCategory',
                        'id'         => $subChronique->_real()->getId(),
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
                    'slug'                 => 'related-1',
                    'publication_datetime' => '2023-01-01T00:00:00+00:00',
                    'cover'                => null,
                    'type_label'           => 'text',
                    'description'          => 'Related 1',
                    'upvotes'              => 0,
                    'downvotes'            => 0,
                    'user_vote'            => null,
                ],
                [
                    '@id'                  => '/api/publications/related-2',
                    '@type'                => 'Publication',
                    'id'                   => $related2->getId(),
                    'title'                => 'Related Publication 2',
                    'sub_category'         => [
                        '@id'        => '/api/publication_sub_categories/' . $subChronique->_real()->getId(),
                        '@type'      => 'PublicationSubCategory',
                        'id'         => $subChronique->_real()->getId(),
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
                    'slug'                 => 'related-2',
                    'publication_datetime' => '2022-01-01T00:00:00+00:00',
                    'cover'                => null,
                    'type_label'           => 'text',
                    'description'          => 'Related 2',
                    'upvotes'              => 0,
                    'downvotes'            => 0,
                    'user_vote'            => null,
                ],
            ],
            'totalItems' => 2,
        ]);
    }

    public function test_get_related_publications_with_nonexistent_slug(): void
    {
        $this->client->request('GET', '/api/publications/nonexistent-slug/related');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context'   => '/api/contexts/RelatedPublication',
            '@id'        => '/api/publications/nonexistent-slug/related',
            '@type'      => 'Collection',
            'member'     => [],
            'totalItems' => 0,
        ]);
    }
}
