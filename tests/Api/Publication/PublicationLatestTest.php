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
use Symfony\Component\HttpFoundation\Response;

#[\Zenstruck\Foundry\Attribute\ResetDatabase]
class PublicationLatestTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_get_latest_publications_default_limit(): void
    {
        $sub = PublicationSubCategoryFactory::new()->asChronique()->create();
        $author = UserFactory::new()->asAdminUser()->create();

        $newest = PublicationFactory::new([
            'author'              => $author,
            'publicationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2025-03-01T00:00:00+00:00'),
            'shortDescription'    => 'Newest',
            'slug'                => 'newest',
            'status'              => Publication::STATUS_ONLINE,
            'subCategory'         => $sub,
            'title'               => 'Newest',
            'type'                => Publication::TYPE_TEXT,
            'viewCache'           => ViewCacheFactory::new(['count' => 0])->create(),
        ])->create();

        $middle = PublicationFactory::new([
            'author'              => $author,
            'publicationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2025-02-01T00:00:00+00:00'),
            'shortDescription'    => 'Middle',
            'slug'                => 'middle',
            'status'              => Publication::STATUS_ONLINE,
            'subCategory'         => $sub,
            'title'               => 'Middle',
            'type'                => Publication::TYPE_TEXT,
            'viewCache'           => ViewCacheFactory::new(['count' => 0])->create(),
        ])->create();

        $oldest = PublicationFactory::new([
            'author'              => $author,
            'publicationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2025-01-01T00:00:00+00:00'),
            'shortDescription'    => 'Oldest',
            'slug'                => 'oldest',
            'status'              => Publication::STATUS_ONLINE,
            'subCategory'         => $sub,
            'title'               => 'Oldest',
            'type'                => Publication::TYPE_TEXT,
            'viewCache'           => ViewCacheFactory::new(['count' => 0])->create(),
        ])->create();

        // Draft must NOT appear
        PublicationFactory::new([
            'author'              => $author,
            'publicationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2026-01-01T00:00:00+00:00'),
            'shortDescription'    => 'Draft',
            'slug'                => 'draft',
            'status'              => Publication::STATUS_DRAFT,
            'subCategory'         => $sub,
            'title'               => 'Draft',
            'type'                => Publication::TYPE_TEXT,
        ])->create();

        $this->client->request('GET', '/api/publications/latest');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context'   => '/api/contexts/Publication',
            '@id'        => '/api/publications/latest',
            '@type'      => 'Collection',
            'member'     => [
                [
                    '@id'                  => '/api/publications/newest',
                    '@type'                => 'Publication',
                    'id'                   => $newest->id,
                    'title'                => 'Newest',
                    'sub_category'         => [
                        '@id'        => '/api/publication_sub_categories/' . $sub->id,
                        '@type'      => 'PublicationSubCategory',
                        'id'         => $sub->id,
                        'title'      => 'Chroniques',
                        'slug'       => 'chroniques',
                        'type_label' => 'publication',
                        'is_course'  => false,
                    ],
                    'author'               => [
                        '@id'      => '/api/users/' . $author->id,
                        '@type'    => 'User',
                        'username' => 'user_admin',
                        'deletion_datetime' => null,
                    ],
                    'slug'                 => 'newest',
                    'publication_datetime' => '2025-03-01T00:00:00+00:00',
                    'cover'                => null,
                    'type_label'           => 'text',
                    'description'          => 'Newest',
                    'upvotes'              => 0,
                    'downvotes'            => 0,
                    'user_vote'            => null,
                ],
                [
                    '@id'                  => '/api/publications/middle',
                    '@type'                => 'Publication',
                    'id'                   => $middle->id,
                    'title'                => 'Middle',
                    'sub_category'         => [
                        '@id'        => '/api/publication_sub_categories/' . $sub->id,
                        '@type'      => 'PublicationSubCategory',
                        'id'         => $sub->id,
                        'title'      => 'Chroniques',
                        'slug'       => 'chroniques',
                        'type_label' => 'publication',
                        'is_course'  => false,
                    ],
                    'author'               => [
                        '@id'      => '/api/users/' . $author->id,
                        '@type'    => 'User',
                        'username' => 'user_admin',
                        'deletion_datetime' => null,
                    ],
                    'slug'                 => 'middle',
                    'publication_datetime' => '2025-02-01T00:00:00+00:00',
                    'cover'                => null,
                    'type_label'           => 'text',
                    'description'          => 'Middle',
                    'upvotes'              => 0,
                    'downvotes'            => 0,
                    'user_vote'            => null,
                ],
                [
                    '@id'                  => '/api/publications/oldest',
                    '@type'                => 'Publication',
                    'id'                   => $oldest->id,
                    'title'                => 'Oldest',
                    'sub_category'         => [
                        '@id'        => '/api/publication_sub_categories/' . $sub->id,
                        '@type'      => 'PublicationSubCategory',
                        'id'         => $sub->id,
                        'title'      => 'Chroniques',
                        'slug'       => 'chroniques',
                        'type_label' => 'publication',
                        'is_course'  => false,
                    ],
                    'author'               => [
                        '@id'      => '/api/users/' . $author->id,
                        '@type'    => 'User',
                        'username' => 'user_admin',
                        'deletion_datetime' => null,
                    ],
                    'slug'                 => 'oldest',
                    'publication_datetime' => '2025-01-01T00:00:00+00:00',
                    'cover'                => null,
                    'type_label'           => 'text',
                    'description'          => 'Oldest',
                    'upvotes'              => 0,
                    'downvotes'            => 0,
                    'user_vote'            => null,
                ],
            ],
            'totalItems' => 3,
        ]);
    }

    public function test_get_latest_publications_with_exclude_id_and_limit(): void
    {
        $sub = PublicationSubCategoryFactory::new()->asChronique()->create();
        $author = UserFactory::new()->asAdminUser()->create();

        $newest = PublicationFactory::new([
            'author'              => $author,
            'publicationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2025-03-01T00:00:00+00:00'),
            'shortDescription'    => 'Newest',
            'slug'                => 'newest',
            'status'              => Publication::STATUS_ONLINE,
            'subCategory'         => $sub,
            'title'               => 'Newest',
            'type'                => Publication::TYPE_TEXT,
            'viewCache'           => ViewCacheFactory::new(['count' => 0])->create(),
        ])->create();

        $middle = PublicationFactory::new([
            'author'              => $author,
            'publicationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2025-02-01T00:00:00+00:00'),
            'shortDescription'    => 'Middle',
            'slug'                => 'middle',
            'status'              => Publication::STATUS_ONLINE,
            'subCategory'         => $sub,
            'title'               => 'Middle',
            'type'                => Publication::TYPE_TEXT,
            'viewCache'           => ViewCacheFactory::new(['count' => 0])->create(),
        ])->create();

        PublicationFactory::new([
            'author'              => $author,
            'publicationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2025-01-01T00:00:00+00:00'),
            'shortDescription'    => 'Oldest',
            'slug'                => 'oldest',
            'status'              => Publication::STATUS_ONLINE,
            'subCategory'         => $sub,
            'title'               => 'Oldest',
            'type'                => Publication::TYPE_TEXT,
            'viewCache'           => ViewCacheFactory::new(['count' => 0])->create(),
        ])->create();

        $this->client->request('GET', sprintf('/api/publications/latest?excludeId=%d&count=1', $newest->id));
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context'   => '/api/contexts/Publication',
            '@id'        => '/api/publications/latest',
            '@type'      => 'Collection',
            'member'     => [
                [
                    '@id'                  => '/api/publications/middle',
                    '@type'                => 'Publication',
                    'id'                   => $middle->id,
                    'title'                => 'Middle',
                    'sub_category'         => [
                        '@id'        => '/api/publication_sub_categories/' . $sub->id,
                        '@type'      => 'PublicationSubCategory',
                        'id'         => $sub->id,
                        'title'      => 'Chroniques',
                        'slug'       => 'chroniques',
                        'type_label' => 'publication',
                        'is_course'  => false,
                    ],
                    'author'               => [
                        '@id'      => '/api/users/' . $author->id,
                        '@type'    => 'User',
                        'username' => 'user_admin',
                        'deletion_datetime' => null,
                    ],
                    'slug'                 => 'middle',
                    'publication_datetime' => '2025-02-01T00:00:00+00:00',
                    'cover'                => null,
                    'type_label'           => 'text',
                    'description'          => 'Middle',
                    'upvotes'              => 0,
                    'downvotes'            => 0,
                    'user_vote'            => null,
                ],
            ],
            'totalItems' => 1,
            'view'       => [
                '@id'   => sprintf('/api/publications/latest?count=1&excludeId=%d', $newest->id),
                '@type' => 'PartialCollectionView',
            ],
        ]);
    }

    public function test_get_latest_publications_filtered_by_sub_category_type(): void
    {
        $publicationCategory = PublicationSubCategoryFactory::new()->asChronique()->create();
        $courseCategory = PublicationSubCategoryFactory::new()->asCourse()->create();
        $author = UserFactory::new()->asAdminUser()->create();

        PublicationFactory::new([
            'author'              => $author,
            'publicationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2025-03-01T00:00:00+00:00'),
            'shortDescription'    => 'Publi',
            'slug'                => 'publi-1',
            'status'              => Publication::STATUS_ONLINE,
            'subCategory'         => $publicationCategory,
            'title'               => 'Publi 1',
            'type'                => Publication::TYPE_TEXT,
            'viewCache'           => ViewCacheFactory::new(['count' => 0])->create(),
        ])->create();

        $course = PublicationFactory::new([
            'author'              => $author,
            'publicationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2025-02-01T00:00:00+00:00'),
            'shortDescription'    => 'Cours',
            'slug'                => 'cours-1',
            'status'              => Publication::STATUS_ONLINE,
            'subCategory'         => $courseCategory,
            'title'               => 'Cours 1',
            'type'                => Publication::TYPE_TEXT,
            'viewCache'           => ViewCacheFactory::new(['count' => 0])->create(),
        ])->create();

        $this->client->request('GET', '/api/publications/latest?subCategoryType=2&count=5');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context'   => '/api/contexts/Publication',
            '@id'        => '/api/publications/latest',
            '@type'      => 'Collection',
            'member'     => [
                [
                    '@id'                  => '/api/publications/cours-1',
                    '@type'                => 'Publication',
                    'id'                   => $course->id,
                    'title'                => 'Cours 1',
                    'sub_category'         => [
                        '@id'        => '/api/publication_sub_categories/' . $courseCategory->id,
                        '@type'      => 'PublicationSubCategory',
                        'id'         => $courseCategory->id,
                        'title'      => 'Cours',
                        'slug'       => 'cours',
                        'type_label' => 'course',
                        'is_course'  => true,
                    ],
                    'author'               => [
                        '@id'      => '/api/users/' . $author->id,
                        '@type'    => 'User',
                        'username' => 'user_admin',
                        'deletion_datetime' => null,
                    ],
                    'slug'                 => 'cours-1',
                    'publication_datetime' => '2025-02-01T00:00:00+00:00',
                    'cover'                => null,
                    'type_label'           => 'text',
                    'description'          => 'Cours',
                    'upvotes'              => 0,
                    'downvotes'            => 0,
                    'user_vote'            => null,
                ],
            ],
            'totalItems' => 1,
            'view'       => [
                '@id'   => '/api/publications/latest?count=5&subCategoryType=2',
                '@type' => 'PartialCollectionView',
            ],
        ]);
    }

    public function test_get_latest_publications_empty(): void
    {
        $this->client->request('GET', '/api/publications/latest');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context'   => '/api/contexts/Publication',
            '@id'        => '/api/publications/latest',
            '@type'      => 'Collection',
            'member'     => [],
            'totalItems' => 0,
        ]);
    }

    public function test_get_latest_publications_rejects_count_out_of_range(): void
    {
        $this->client->request('GET', '/api/publications/latest?count=0');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id'      => '/api/validation_errors/04b91c99-a946-4221-afc5-e65ebac401eb',
            '@type'    => 'ConstraintViolation',
            'status'   => 422,
            'violations' => [
                [
                    'propertyPath' => 'count',
                    'message'      => 'Cette valeur doit être comprise entre 1 et 20.',
                    'code'         => '04b91c99-a946-4221-afc5-e65ebac401eb',
                ],
            ],
            'detail'      => 'count: Cette valeur doit être comprise entre 1 et 20.',
            'description' => 'count: Cette valeur doit être comprise entre 1 et 20.',
            'type'        => '/validation_errors/04b91c99-a946-4221-afc5-e65ebac401eb',
            'title'       => 'An error occurred',
        ]);
    }

    public function test_get_latest_publications_rejects_negative_exclude_id(): void
    {
        $this->client->request('GET', '/api/publications/latest?excludeId=-1');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id'      => '/api/validation_errors/778b7ae0-84d3-481a-9dec-35fdb64b1d78',
            '@type'    => 'ConstraintViolation',
            'status'   => 422,
            'violations' => [
                [
                    'propertyPath' => 'exclude_id',
                    'message'      => 'Cette valeur doit être strictement positive.',
                    'code'         => '778b7ae0-84d3-481a-9dec-35fdb64b1d78',
                ],
            ],
            'detail'      => 'exclude_id: Cette valeur doit être strictement positive.',
            'description' => 'exclude_id: Cette valeur doit être strictement positive.',
            'type'        => '/validation_errors/778b7ae0-84d3-481a-9dec-35fdb64b1d78',
            'title'       => 'An error occurred',
        ]);
    }

    public function test_get_latest_publications_rejects_invalid_sub_category_type(): void
    {
        $this->client->request('GET', '/api/publications/latest?subCategoryType=3');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id'      => '/api/validation_errors/04b91c99-a946-4221-afc5-e65ebac401eb',
            '@type'    => 'ConstraintViolation',
            'status'   => 422,
            'violations' => [
                [
                    'propertyPath' => 'sub_category_type',
                    'message'      => 'Cette valeur doit être comprise entre 1 et 2.',
                    'code'         => '04b91c99-a946-4221-afc5-e65ebac401eb',
                ],
            ],
            'detail'      => 'sub_category_type: Cette valeur doit être comprise entre 1 et 2.',
            'description' => 'sub_category_type: Cette valeur doit être comprise entre 1 et 2.',
            'type'        => '/validation_errors/04b91c99-a946-4221-afc5-e65ebac401eb',
            'title'       => 'An error occurred',
        ]);
    }
}
