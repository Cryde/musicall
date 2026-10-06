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
                        'type_label' => 'publication',
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
                    'type_label'           => 'text',
                    'description'          => 'Petite description de la publication 2',
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
                        'type_label' => 'publication',
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
                'type_label' => 'publication',
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
            'type_label' => 'text',
            'description' => $publication->shortDescription,
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
