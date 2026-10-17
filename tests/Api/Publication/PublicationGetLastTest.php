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
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class PublicationGetLastTest extends ApiTestCase
{
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
        ])->create();

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
        ])->create();

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
        ])->create();

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
        ])->create();

        // This one should not appear (not online)
        PublicationFactory::new([
            'author'     => $author,
            'status'     => Publication::STATUS_DRAFT,
            'subCategory' => $sub,
        ])->create();

        $this->client->loginUser($currentUser);
        $this->client->request('GET', '/api/last-publications');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context'   => '/api/contexts/Publication',
            '@id'        => '/api/last-publications',
            '@type'      => 'Collection',
            'member'     => [
                [
                    '@id'                  => '/api/publications/publication-5',
                    '@type'                => 'Publication',
                    'id'                   => $pub5->id,
                    'title'                => 'Publication 5',
                    'sub_category'         => [
                        '@type'      => 'SubCategory',
                        'id'         => $sub->id,
                        'title'      => 'Chroniques',
                        'slug'       => 'chroniques',
                        'is_course'  => false,
                    ],
                    'author'               => [
                        '@type'    => 'Author',
                        'username' => 'user_admin',
                        'display_name' => 'user_admin',
                        'deletion_datetime' => null,
                    ],
                    'slug'                 => 'publication-5',
                    'publication_datetime' => '2024-01-01T00:00:00+00:00',
                    'cover'                => null,
                    'description'          => 'Description 5',
                    'language'             => 'fr',
                    'upvotes'              => 0,
                    'downvotes'            => 0,
                    'user_vote'            => null,
                ],
                [
                    '@id'                  => '/api/publications/publication-4',
                    '@type'                => 'Publication',
                    'id'                   => $pub4->id,
                    'title'                => 'Publication 4',
                    'sub_category'         => [
                        '@type'      => 'SubCategory',
                        'id'         => $sub->id,
                        'title'      => 'Chroniques',
                        'slug'       => 'chroniques',
                        'is_course'  => false,
                    ],
                    'author'               => [
                        '@type'    => 'Author',
                        'username' => 'user_admin',
                        'display_name' => 'user_admin',
                        'deletion_datetime' => null,
                    ],
                    'slug'                 => 'publication-4',
                    'publication_datetime' => '2023-01-01T00:00:00+00:00',
                    'cover'                => null,
                    'description'          => 'Description 4',
                    'language'             => 'fr',
                    'upvotes'              => 0,
                    'downvotes'            => 3,
                    'user_vote'            => null,
                ],
                [
                    '@id'                  => '/api/publications/publication-3',
                    '@type'                => 'Publication',
                    'id'                   => $pub3->id,
                    'title'                => 'Publication 3',
                    'sub_category'         => [
                        '@type'      => 'SubCategory',
                        'id'         => $sub->id,
                        'title'      => 'Chroniques',
                        'slug'       => 'chroniques',
                        'is_course'  => false,
                    ],
                    'author'               => [
                        '@type'    => 'Author',
                        'username' => 'user_admin',
                        'display_name' => 'user_admin',
                        'deletion_datetime' => null,
                    ],
                    'slug'                 => 'publication-3',
                    'publication_datetime' => '2022-01-01T00:00:00+00:00',
                    'cover'                => null,
                    'description'          => 'Description 3',
                    'language'             => 'fr',
                    'upvotes'              => 5,
                    'downvotes'            => 0,
                    'user_vote'            => 1,
                ],
                [
                    '@id'                  => '/api/publications/publication-2',
                    '@type'                => 'Publication',
                    'id'                   => $pub2->id,
                    'title'                => 'Publication 2',
                    'sub_category'         => [
                        '@type'      => 'SubCategory',
                        'id'         => $sub->id,
                        'title'      => 'Chroniques',
                        'slug'       => 'chroniques',
                        'is_course'  => false,
                    ],
                    'author'               => [
                        '@type'    => 'Author',
                        'username' => 'user_admin',
                        'display_name' => 'user_admin',
                        'deletion_datetime' => null,
                    ],
                    'slug'                 => 'publication-2',
                    'publication_datetime' => '2021-01-01T00:00:00+00:00',
                    'cover'                => null,
                    'description'          => 'Description 2',
                    'language'             => 'fr',
                    'upvotes'              => 2,
                    'downvotes'            => 1,
                    'user_vote'            => -1,
                ],
            ],
            'totalItems' => 4,
        ]);
    }

    public function test_get_last_publications_in_english(): void
    {
        $news = PublicationSubCategoryFactory::new()->asNews()->create();
        $author = UserFactory::new()->asAdminUser()->create();
        // The newest, so leaving it out is the filter's doing, not the limit's.
        PublicationFactory::new([
            'author' => $author, 'subCategory' => $news, 'status' => Publication::STATUS_ONLINE,
            'type' => Publication::TYPE_TEXT, 'language' => 'fr', 'slug' => 'un-article',
            'publicationDatetime' => new \DateTime('2022-01-03 10:00:00'),
        ])->create();
        $english = PublicationFactory::new([
            'author' => $author, 'subCategory' => $news, 'status' => Publication::STATUS_ONLINE,
            'type' => Publication::TYPE_TEXT, 'language' => 'en', 'title' => 'An article', 'slug' => 'an-article',
            'shortDescription' => 'In English', 'publicationDatetime' => new \DateTime('2022-01-02 10:00:00'),
        ])->create();
        $video = PublicationFactory::new([
            'author' => $author, 'subCategory' => $news, 'status' => Publication::STATUS_ONLINE,
            'type' => Publication::TYPE_VIDEO, 'title' => 'Une video', 'slug' => 'v-une-video',
            'publicationDatetime' => new \DateTime('2022-01-01 10:00:00'),
        ])->create();

        $this->client->request('GET', '/api/last-publications', [], [], ['HTTP_X_LOCALE' => 'en']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Publication',
            '@id' => '/api/last-publications',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/publications/an-article',
                    '@type' => 'Publication',
                    'id' => $english->id,
                    'title' => 'An article',
                    'sub_category' => [
                        '@type' => 'SubCategory',
                        'id' => $news->id,
                        'title' => 'News',
                        'slug' => 'news',
                        'is_course' => false,
                    ],
                    'author' => [
                        '@type' => 'Author',
                        'username' => 'user_admin',
                        'display_name' => 'user_admin',
                        'deletion_datetime' => null,
                    ],
                    'slug' => 'an-article',
                    'publication_datetime' => '2022-01-02T10:00:00+00:00',
                    'cover' => null,
                    'description' => 'In English',
                    'language' => 'en',
                    'upvotes' => 0,
                    'downvotes' => 0,
                    'user_vote' => null,
                ],
                [
                    '@id' => '/api/publications/v-une-video',
                    '@type' => 'Publication',
                    'id' => $video->id,
                    'title' => 'Une video',
                    'sub_category' => [
                        '@type' => 'SubCategory',
                        'id' => $news->id,
                        'title' => 'News',
                        'slug' => 'news',
                        'is_course' => false,
                    ],
                    'author' => [
                        '@type' => 'Author',
                        'username' => 'user_admin',
                        'display_name' => 'user_admin',
                        'deletion_datetime' => null,
                    ],
                    'slug' => 'v-une-video',
                    'publication_datetime' => '2022-01-01T10:00:00+00:00',
                    'cover' => null,
                    'description' => null,
                    'language' => null,
                    'upvotes' => 0,
                    'downvotes' => 0,
                    'user_vote' => null,
                ],
            ],
            'totalItems' => 2,
        ]);
    }
}
