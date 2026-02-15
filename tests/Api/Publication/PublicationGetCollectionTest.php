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

class PublicationGetCollectionTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_get_publications(): void
    {
        $sub = PublicationSubCategoryFactory::new()->asChronique()->create();
        $sub2 = PublicationSubCategoryFactory::new()->asNews()->create();
        $author = UserFactory::new()->asAdminUser()->create();
        $currentUser = UserFactory::new()->asBaseUser()->create();
        $otherUser = UserFactory::new()->create();

        // pub1: has votes (3 up, 1 down), current user voted up
        $voteCache1 = VoteCacheFactory::new(['upvoteCount' => 3, 'downvoteCount' => 1])->create();
        VoteFactory::new([
            'voteCache' => $voteCache1, 'user' => $currentUser, 'value' => 1,
            'entityType' => 'app_publication', 'identifier' => 'test',
        ])->create();
        VoteFactory::new([
            'voteCache' => $voteCache1, 'user' => $otherUser, 'value' => 1,
            'entityType' => 'app_publication', 'identifier' => 'test2',
        ])->create();

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
            'voteCache'           => $voteCache1,
        ])->create()->_real();

        // pub2: has votes (1 up, 2 down), current user did NOT vote
        $voteCache2 = VoteCacheFactory::new(['upvoteCount' => 1, 'downvoteCount' => 2])->create();
        VoteFactory::new([
            'voteCache' => $voteCache2, 'user' => $otherUser, 'value' => -1,
            'entityType' => 'app_publication', 'identifier' => 'test3',
        ])->create();

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
            'voteCache'           => $voteCache2,
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

        $this->client->loginUser($currentUser->_real());
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
                        '@type' => 'SubCategory',
                        'id'         => $sub->_real()->getId(),
                        'title'      => 'Chroniques',
                        'slug'       => 'chroniques',
                        'type_label' => 'publication',
                        'is_course'  => false,
                    ],
                    'author'               => [
                        '@type' => 'Author',
                        'username' => 'user_admin',
                        'deletion_datetime' => null,
                    ],
                    'slug'                 => 'titre-de-la-publication-2',
                    'publication_datetime' => '2000-01-02T02:03:04+00:00',
                    'cover'                => null,
                    'type_label'           => 'text',
                    'description'          => 'Petite description de la publication 2',
                    'upvotes'              => 1,
                    'downvotes'            => 2,
                    'user_vote'            => null,
                ],
                [
                    '@id'                  => '/api/publications/titre-de-la-publication-1',
                    '@type'                => 'Publication',
                    'id'                   => $pub1->getId(),
                    'title'                => 'Titre de la publication 1',
                    'sub_category'         => [
                        '@type' => 'SubCategory',
                        'id'         => $sub->_real()->getId(),
                        'title'      => 'Chroniques',
                        'slug'       => 'chroniques',
                        'type_label' => 'publication',
                        'is_course'  => false,
                    ],
                    'author'               => [
                        '@type' => 'Author',
                        'username' => 'user_admin',
                        'deletion_datetime' => null,
                    ],
                    'slug'                 => 'titre-de-la-publication-1',
                    'publication_datetime' => '2022-01-02T02:03:04+00:00',
                    'cover'                => null,
                    'type_label'           => 'text',
                    'description'          => 'Petite description de la publication 1',
                    'upvotes'              => 3,
                    'downvotes'            => 1,
                    'user_vote'            => 1,
                ],
            ],
            'totalItems' => 2,
            'view'       => [
                '@id'   => '/api/publications?order%5Bpublication_datetime%5D=asc&sub_category.slug=chroniques',
                '@type' => 'PartialCollectionView',
            ],
            'search'     => [
                '@type'                  => 'IriTemplate',
                'template'               => '/api/publications{?sub_category.slug,sub_category.type,order[publication_datetime],page}',
                'variableRepresentation' => 'BasicRepresentation',
                'mapping'                => [
                    [
                        '@type'    => 'IriTemplateMapping',
                        'variable' => 'sub_category.slug',
                        'property' => 'sub_category.slug',
                        'required' => false,
                    ],
                    [
                        '@type'    => 'IriTemplateMapping',
                        'variable' => 'sub_category.type',
                        'property' => 'sub_category.type',
                        'required' => false,
                    ],
                    [
                        '@type'    => 'IriTemplateMapping',
                        'variable' => 'order[publication_datetime]',
                        'property' => 'publication_datetime',
                        'required' => false,
                    ],
                    [
                        '@type'    => 'IriTemplateMapping',
                        'variable' => 'page',
                        'property' => 'page',
                        'required' => false,
                    ],
                ],
            ],
        ]);
    }
}
