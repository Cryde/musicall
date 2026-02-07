<?php

namespace App\Tests\Api\Publication;

use App\Entity\Publication;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Metric\VoteCacheFactory;
use App\Tests\Factory\Metric\VoteFactory;
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
        $currentUser = UserFactory::new()->asBaseUser()->create();
        $otherUser = UserFactory::new()->create();

        // pub1: no votes
        PublicationFactory::new([
            'author'              => $author,
            'publicationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2020-01-01T00:00:00+00:00'),
            'shortDescription'    => 'Description 1',
            'slug'                => 'publication-1',
            'status'              => Publication::STATUS_ONLINE,
            'subCategory'         => $sub,
            'title'               => 'Publication 1',
            'type'                => Publication::TYPE_TEXT,
            'viewCache'           => ViewCacheFactory::new(['count' => 10])->create(),
        ])->create();

        // pub2: has votes (2 up, 1 down), current user voted down
        $voteCache2 = VoteCacheFactory::new(['upvoteCount' => 2, 'downvoteCount' => 1])->create();
        VoteFactory::new([
            'voteCache' => $voteCache2, 'user' => $currentUser, 'value' => -1,
            'entityType' => 'app_publication', 'identifier' => 'test',
        ])->create();
        VoteFactory::new([
            'voteCache' => $voteCache2, 'user' => $otherUser, 'value' => 1,
            'entityType' => 'app_publication', 'identifier' => 'test2',
        ])->create();

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
            'voteCache'           => $voteCache2,
        ])->create()->_real();

        // pub3: has votes (5 up, 0 down), current user voted up
        $voteCache3 = VoteCacheFactory::new(['upvoteCount' => 5, 'downvoteCount' => 0])->create();
        VoteFactory::new([
            'voteCache' => $voteCache3, 'user' => $currentUser, 'value' => 1,
            'entityType' => 'app_publication', 'identifier' => 'test3',
        ])->create();

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
            'voteCache'           => $voteCache3,
        ])->create()->_real();

        // pub4: has votes (0 up, 3 down), current user did NOT vote
        $voteCache4 = VoteCacheFactory::new(['upvoteCount' => 0, 'downvoteCount' => 3])->create();
        VoteFactory::new([
            'voteCache' => $voteCache4, 'user' => $otherUser, 'value' => -1,
            'entityType' => 'app_publication', 'identifier' => 'test4',
        ])->create();

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
            'voteCache'           => $voteCache4,
        ])->create()->_real();

        // pub5: no votes
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

        $this->client->loginUser($currentUser->_real());
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
                    'upvotes'              => 0,
                    'downvotes'            => 0,
                    'user_vote'            => null,
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
                    'upvotes'              => 0,
                    'downvotes'            => 3,
                    'user_vote'            => null,
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
                    'upvotes'              => 5,
                    'downvotes'            => 0,
                    'user_vote'            => 1,
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
                    'upvotes'              => 2,
                    'downvotes'            => 1,
                    'user_vote'            => -1,
                ],
            ],
            'totalItems' => 4,
        ]);
    }
}
