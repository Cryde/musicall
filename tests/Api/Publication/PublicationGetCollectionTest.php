<?php

namespace App\Tests\Api\Publication;

use App\Entity\Publication;
use App\Entity\PublicationSubCategory;
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
class PublicationGetCollectionTest extends ApiTestCase
{
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
        ])->create();

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
        ])->create();

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

        $this->client->loginUser($currentUser);
        $this->client->request('GET', '/api/publications', [
            'order' => ['publication_datetime' => 'asc'],
            'sub_category.slug' => 'chroniques'
        ]);
        $this->assertResponseHeaderSame('content-type', 'application/ld+json');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context'         => '/api/contexts/Publication',
            '@id'              => '/api/publications',
            '@type'            => 'Collection',
            'member'     => [
                [
                    '@id'                  => '/api/publications/titre-de-la-publication-2',
                    '@type'                => 'Publication',
                    'id'                   => $pub2->id,
                    'title'                => 'Titre de la publication 2',
                    'sub_category'         => [
                        '@type' => 'SubCategory',
                        'id'         => $sub->id,
                        'title'      => 'Chroniques',
                        'slug'       => 'chroniques',
                        'is_course'  => false,
                    ],
                    'author'               => [
                        '@type' => 'Author',
                        'username' => 'user_admin',
                        'display_name' => 'user_admin',
                        'deletion_datetime' => null,
                    ],
                    'slug'                 => 'titre-de-la-publication-2',
                    'publication_datetime' => '2000-01-02T02:03:04+00:00',
                    'cover'                => null,
                    'description'          => 'Petite description de la publication 2',
                    'language'             => 'fr',
                    'upvotes'              => 1,
                    'downvotes'            => 2,
                    'user_vote'            => null,
                ],
                [
                    '@id'                  => '/api/publications/titre-de-la-publication-1',
                    '@type'                => 'Publication',
                    'id'                   => $pub1->id,
                    'title'                => 'Titre de la publication 1',
                    'sub_category'         => [
                        '@type' => 'SubCategory',
                        'id'         => $sub->id,
                        'title'      => 'Chroniques',
                        'slug'       => 'chroniques',
                        'is_course'  => false,
                    ],
                    'author'               => [
                        '@type' => 'Author',
                        'username' => 'user_admin',
                        'display_name' => 'user_admin',
                        'deletion_datetime' => null,
                    ],
                    'slug'                 => 'titre-de-la-publication-1',
                    'publication_datetime' => '2022-01-02T02:03:04+00:00',
                    'cover'                => null,
                    'description'          => 'Petite description de la publication 1',
                    'language'             => 'fr',
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
                'template'               => '/api/publications{?sub_category.slug,sub_category.type,order[publication_datetime],tag.slug,page}',
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
                        'variable' => 'tag.slug',
                        'property' => 'tag.slug',
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

    public function test_a_french_listing_shows_its_texts_and_every_video(): void
    {
        $news = PublicationSubCategoryFactory::new()->asNews()->create();
        $author = UserFactory::new()->asAdminUser()->create();
        $french = PublicationFactory::new([
            'author' => $author, 'subCategory' => $news, 'status' => Publication::STATUS_ONLINE,
            'type' => Publication::TYPE_TEXT, 'language' => 'fr', 'title' => 'Un article', 'slug' => 'un-article',
            'shortDescription' => 'En français', 'publicationDatetime' => new \DateTime('2022-01-01 10:00:00'),
        ])->create();
        $english = PublicationFactory::new([
            'author' => $author, 'subCategory' => $news, 'status' => Publication::STATUS_ONLINE,
            'type' => Publication::TYPE_TEXT, 'language' => 'en', 'title' => 'An article', 'slug' => 'an-article',
            'shortDescription' => 'In English', 'publicationDatetime' => new \DateTime('2022-01-02 10:00:00'),
        ])->create();
        $video = PublicationFactory::new([
            'author' => $author, 'subCategory' => $news, 'status' => Publication::STATUS_ONLINE,
            'type' => Publication::TYPE_VIDEO, 'title' => 'Une video', 'slug' => 'v-une-video',
            'publicationDatetime' => new \DateTime('2022-01-03 10:00:00'),
        ])->create();

        $this->client->request('GET', '/api/publications', [
            'order' => ['publication_datetime' => 'asc'],
            'sub_category.slug' => 'news',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Publication',
            '@id' => '/api/publications',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/publications/un-article',
                    '@type' => 'Publication',
                    'id' => $french->id,
                    'title' => 'Un article',
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
                    'slug' => 'un-article',
                    'publication_datetime' => '2022-01-01T10:00:00+00:00',
                    'cover' => null,
                    'description' => 'En français',
                    'language' => 'fr',
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
                    'publication_datetime' => '2022-01-03T10:00:00+00:00',
                    'cover' => null,
                    'description' => null,
                    'language' => null,
                    'upvotes' => 0,
                    'downvotes' => 0,
                    'user_vote' => null,
                ],
            ],
            'totalItems' => 2,
            'view' => [
                '@id' => '/api/publications?order%5Bpublication_datetime%5D=asc&sub_category.slug=news',
                '@type' => 'PartialCollectionView',
            ],
            'search' => $this->expectedSearch(),
        ]);
    }

    public function test_an_english_listing_shows_its_texts_and_every_video(): void
    {
        $news = PublicationSubCategoryFactory::new()->asNews()->create();
        $author = UserFactory::new()->asAdminUser()->create();
        $french = PublicationFactory::new([
            'author' => $author, 'subCategory' => $news, 'status' => Publication::STATUS_ONLINE,
            'type' => Publication::TYPE_TEXT, 'language' => 'fr', 'title' => 'Un article', 'slug' => 'un-article',
            'shortDescription' => 'En français', 'publicationDatetime' => new \DateTime('2022-01-01 10:00:00'),
        ])->create();
        $english = PublicationFactory::new([
            'author' => $author, 'subCategory' => $news, 'status' => Publication::STATUS_ONLINE,
            'type' => Publication::TYPE_TEXT, 'language' => 'en', 'title' => 'An article', 'slug' => 'an-article',
            'shortDescription' => 'In English', 'publicationDatetime' => new \DateTime('2022-01-02 10:00:00'),
        ])->create();
        $video = PublicationFactory::new([
            'author' => $author, 'subCategory' => $news, 'status' => Publication::STATUS_ONLINE,
            'type' => Publication::TYPE_VIDEO, 'title' => 'Une video', 'slug' => 'v-une-video',
            'publicationDatetime' => new \DateTime('2022-01-03 10:00:00'),
        ])->create();

        $this->client->request('GET', '/api/publications', [
            'order' => ['publication_datetime' => 'asc'],
            'sub_category.slug' => 'news',
        ], [], ['HTTP_X_LOCALE' => 'en']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Publication',
            '@id' => '/api/publications',
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
                    'publication_datetime' => '2022-01-03T10:00:00+00:00',
                    'cover' => null,
                    'description' => null,
                    'language' => null,
                    'upvotes' => 0,
                    'downvotes' => 0,
                    'user_vote' => null,
                ],
            ],
            'totalItems' => 2,
            'view' => [
                '@id' => '/api/publications?order%5Bpublication_datetime%5D=asc&sub_category.slug=news',
                '@type' => 'PartialCollectionView',
            ],
            'search' => $this->expectedSearch(),
        ]);
    }

    public function test_an_english_course_listing_shows_its_courses_and_every_video(): void
    {
        // A slug no catalogue names, so the title reads the same in every locale.
        $lessons = PublicationSubCategoryFactory::new([
            'title' => 'Lessons', 'slug' => 'test-lessons', 'position' => 1, 'type' => PublicationSubCategory::TYPE_COURSE,
        ])->create();
        $author = UserFactory::new()->asAdminUser()->create();
        PublicationFactory::new([
            'author' => $author, 'subCategory' => $lessons, 'status' => Publication::STATUS_ONLINE,
            'type' => Publication::TYPE_TEXT, 'language' => 'fr', 'slug' => 'un-cours',
        ])->create();
        $english = PublicationFactory::new([
            'author' => $author, 'subCategory' => $lessons, 'status' => Publication::STATUS_ONLINE,
            'type' => Publication::TYPE_TEXT, 'language' => 'en', 'title' => 'A lesson', 'slug' => 'a-lesson',
            'shortDescription' => 'In English', 'publicationDatetime' => new \DateTime('2022-01-02 10:00:00'),
        ])->create();
        $video = PublicationFactory::new([
            'author' => $author, 'subCategory' => $lessons, 'status' => Publication::STATUS_ONLINE,
            'type' => Publication::TYPE_VIDEO, 'title' => 'A video lesson', 'slug' => 'v-a-video-lesson',
            'publicationDatetime' => new \DateTime('2022-01-03 10:00:00'),
        ])->create();

        $this->client->request('GET', '/api/publications', [
            'order' => ['publication_datetime' => 'asc'],
            'sub_category.type' => '2',
        ], [], ['HTTP_X_LOCALE' => 'en']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Publication',
            '@id' => '/api/publications',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/publications/a-lesson',
                    '@type' => 'Publication',
                    'id' => $english->id,
                    'title' => 'A lesson',
                    'sub_category' => [
                        '@type' => 'SubCategory',
                        'id' => $lessons->id,
                        'title' => 'Lessons',
                        'slug' => 'test-lessons',
                        'is_course' => true,
                    ],
                    'author' => [
                        '@type' => 'Author',
                        'username' => 'user_admin',
                        'display_name' => 'user_admin',
                        'deletion_datetime' => null,
                    ],
                    'slug' => 'a-lesson',
                    'publication_datetime' => '2022-01-02T10:00:00+00:00',
                    'cover' => null,
                    'description' => 'In English',
                    'language' => 'en',
                    'upvotes' => 0,
                    'downvotes' => 0,
                    'user_vote' => null,
                ],
                [
                    '@id' => '/api/publications/v-a-video-lesson',
                    '@type' => 'Publication',
                    'id' => $video->id,
                    'title' => 'A video lesson',
                    'sub_category' => [
                        '@type' => 'SubCategory',
                        'id' => $lessons->id,
                        'title' => 'Lessons',
                        'slug' => 'test-lessons',
                        'is_course' => true,
                    ],
                    'author' => [
                        '@type' => 'Author',
                        'username' => 'user_admin',
                        'display_name' => 'user_admin',
                        'deletion_datetime' => null,
                    ],
                    'slug' => 'v-a-video-lesson',
                    'publication_datetime' => '2022-01-03T10:00:00+00:00',
                    'cover' => null,
                    'description' => null,
                    'language' => null,
                    'upvotes' => 0,
                    'downvotes' => 0,
                    'user_vote' => null,
                ],
            ],
            'totalItems' => 2,
            'view' => [
                '@id' => '/api/publications?order%5Bpublication_datetime%5D=asc&sub_category.type=2',
                '@type' => 'PartialCollectionView',
            ],
            'search' => $this->expectedSearch(),
        ]);
    }

    /**
     * One author per naming case. The profile is joined into the list query, so naming them costs no
     * query per author (#1118).
     */
    public function test_authors_are_named_by_their_public_profile_name_next_to_their_username(): void
    {
        $sub = PublicationSubCategoryFactory::new()->asChronique()->create();
        $named = UserFactory::new()->create(['username' => 'androidtest_123', 'email' => 'androidtest@test.com']);
        $named->profile->displayName = 'Alexandre Martin';
        $private = UserFactory::new()->create(['username' => 'crydetest', 'email' => 'crydetest@test.com']);
        $private->profile->displayName = 'Samuel Dupont';
        $private->profile->isPublic = false;
        $departed = UserFactory::new()->create([
            'username' => 'deleted_c7c9f2e1',
            'email' => 'deleted_c7c9f2e1@email.com',
            'deletionDatetime' => new \DateTimeImmutable('2024-06-01 09:00:00'),
        ]);
        self::getContainer()->get('doctrine')->getManager()->flush();

        $publications = [];
        foreach ([[$named, 'alex'], [$private, 'sam'], [$departed, 'orphan']] as $index => [$author, $slug]) {
            $publications[] = PublicationFactory::new([
                'author' => $author,
                'publicationDatetime' => new \DateTime(sprintf('2022-01-0%d 10:00:00', $index + 1)),
                'shortDescription' => 'Description ' . $slug,
                'slug' => $slug,
                'status' => Publication::STATUS_ONLINE,
                'subCategory' => $sub,
                'title' => 'Titre ' . $slug,
                'type' => Publication::TYPE_TEXT,
            ])->create();
        }

        $this->client->enableProfiler();
        self::getContainer()->get('doctrine')->getManager()->clear();
        self::getContainer()->get('doctrine.debug_data_holder')->reset();
        $this->client->request('GET', '/api/publications', [
            'order' => ['publication_datetime' => 'asc'],
            'sub_category.slug' => 'chroniques',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Publication',
            '@id' => '/api/publications',
            '@type' => 'Collection',
            'member' => [
                $this->expectedListItem($publications[0], $sub, 'androidtest_123', 'Alexandre Martin', null),
                // A private profile keeps its name to itself.
                $this->expectedListItem($publications[1], $sub, 'crydetest', 'crydetest', null),
                $this->expectedListItem($publications[2], $sub, 'deleted_c7c9f2e1', 'Utilisateur supprimé', '2024-06-01T09:00:00+00:00'),
            ],
            'totalItems' => 3,
            'view' => [
                '@id' => '/api/publications?order%5Bpublication_datetime%5D=asc&sub_category.slug=chroniques',
                '@type' => 'PartialCollectionView',
            ],
            'search' => $this->expectedSearch(),
        ]);

        $this->assertNoQueryReadsTable('user_profile', 'An author profile must come with the list, never in a query of its own');
    }

    /**
     * @return array<string, mixed>
     */
    private function expectedListItem(Publication $publication, object $sub, string $username, string $displayName, ?string $deletionDatetime): array
    {
        return [
            '@id' => '/api/publications/' . $publication->slug,
            '@type' => 'Publication',
            'id' => $publication->id,
            'title' => $publication->title,
            'sub_category' => [
                '@type' => 'SubCategory',
                'id' => $sub->id,
                'title' => 'Chroniques',
                'slug' => 'chroniques',
                'is_course' => false,
            ],
            'author' => [
                '@type' => 'Author',
                'username' => $username,
                'display_name' => $displayName,
                'deletion_datetime' => $deletionDatetime,
            ],
            'slug' => $publication->slug,
            'publication_datetime' => \DateTimeImmutable::createFromInterface($publication->publicationDatetime)->format(\DateTimeInterface::ATOM),
            'cover' => null,
            'description' => $publication->shortDescription,
            'language' => 'fr',
            'upvotes' => 0,
            'downvotes' => 0,
            'user_vote' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function expectedSearch(): array
    {
        return [
            '@type'                  => 'IriTemplate',
            'template'               => '/api/publications{?sub_category.slug,sub_category.type,order[publication_datetime],tag.slug,page}',
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
                    'variable' => 'tag.slug',
                    'property' => 'tag.slug',
                    'required' => false,
                ],
                [
                    '@type'    => 'IriTemplateMapping',
                    'variable' => 'page',
                    'property' => 'page',
                    'required' => false,
                ],
            ],
        ];
    }
}
