<?php

declare(strict_types=1);

namespace App\Tests\Api\Publication;

use App\Entity\Publication;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Metric\ViewCacheFactory;
use App\Tests\Factory\Publication\PublicationFactory;
use App\Tests\Factory\Publication\PublicationSubCategoryFactory;
use App\Tests\Factory\Publication\TagFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\Common\Collections\ArrayCollection;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class PublicationRelatedTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_get_related_publications_fallbacks_to_subcategory_when_no_tags(): void
    {
        $subChronique = PublicationSubCategoryFactory::new()->asChronique()->create();
        $subNews = PublicationSubCategoryFactory::new()->asNews()->create();
        $author = UserFactory::new()->asAdminUser()->create();

        PublicationFactory::new([
            'author'              => $author,
            'publicationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2024-01-01T00:00:00+00:00'),
            'shortDescription'    => 'Current publication',
            'slug'                => 'current-publication',
            'status'              => Publication::STATUS_ONLINE,
            'subCategory'         => $subChronique,
            'title'               => 'Current Publication',
            'type'                => Publication::TYPE_TEXT,
            'viewCache'           => ViewCacheFactory::new(['count' => 10])->create(),
        ])->create();

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
        ])->create();

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
        ])->create();

        // Different subcategory — must NOT appear
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

        // Draft — must NOT appear
        PublicationFactory::new([
            'author'      => $author,
            'slug'        => 'draft-publication',
            'status'      => Publication::STATUS_DRAFT,
            'subCategory' => $subChronique,
        ])->create();

        $this->client->request('GET', '/api/publications/current-publication/related');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context'   => '/api/contexts/Publication',
            '@id'        => '/api/publications/current-publication/related',
            '@type'      => 'Collection',
            'member'     => [
                [
                    '@id'                  => '/api/publications/related-1',
                    '@type'                => 'Publication',
                    'id'                   => $related1->id,
                    'title'                => 'Related Publication 1',
                    'sub_category'         => [
                        '@type'      => 'SubCategory',
                        'id'         => $subChronique->id,
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
                    'slug'                 => 'related-1',
                    'publication_datetime' => '2023-01-01T00:00:00+00:00',
                    'cover'                => null,
                    'description'          => 'Related 1',
                    'language'             => 'fr',
                    'upvotes'              => 0,
                    'downvotes'            => 0,
                    'user_vote'            => null,
                ],
                [
                    '@id'                  => '/api/publications/related-2',
                    '@type'                => 'Publication',
                    'id'                   => $related2->id,
                    'title'                => 'Related Publication 2',
                    'sub_category'         => [
                        '@type'      => 'SubCategory',
                        'id'         => $subChronique->id,
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
                    'slug'                 => 'related-2',
                    'publication_datetime' => '2022-01-01T00:00:00+00:00',
                    'cover'                => null,
                    'description'          => 'Related 2',
                    'language'             => 'fr',
                    'upvotes'              => 0,
                    'downvotes'            => 0,
                    'user_vote'            => null,
                ],
            ],
            'totalItems' => 2,
        ]);
    }

    public function test_get_related_publications_ranks_by_shared_tags_then_fallback(): void
    {
        $subChronique = PublicationSubCategoryFactory::new()->asChronique()->create();
        $subNews = PublicationSubCategoryFactory::new()->asNews()->create();
        $author = UserFactory::new()->asAdminUser()->create();

        $tagMetal = TagFactory::new(['label' => 'Metal', 'slug' => 'metal'])->create();
        $tagJazz = TagFactory::new(['label' => 'Jazz', 'slug' => 'jazz'])->create();
        $tagBlues = TagFactory::new(['label' => 'Blues', 'slug' => 'blues'])->create();

        PublicationFactory::new([
            'author'              => $author,
            'publicationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2025-01-01T00:00:00+00:00'),
            'shortDescription'    => 'Current',
            'slug'                => 'current',
            'status'              => Publication::STATUS_ONLINE,
            'subCategory'         => $subChronique,
            'title'               => 'Current',
            'type'                => Publication::TYPE_TEXT,
            'viewCache'           => ViewCacheFactory::new(['count' => 0])->create(),
            'tags'                => new ArrayCollection([$tagMetal, $tagJazz]),
        ])->create();

        // Shares 2 tags (Metal + Jazz) — should rank first
        $twoShared = PublicationFactory::new([
            'author'              => $author,
            'publicationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2024-01-01T00:00:00+00:00'),
            'shortDescription'    => 'Two shared',
            'slug'                => 'two-shared',
            'status'              => Publication::STATUS_ONLINE,
            'subCategory'         => $subNews,
            'title'               => 'Two Shared Tags',
            'type'                => Publication::TYPE_TEXT,
            'viewCache'           => ViewCacheFactory::new(['count' => 0])->create(),
            'tags'                => new ArrayCollection([$tagMetal, $tagJazz]),
        ])->create();

        // Shares 1 tag (Metal) — should rank second
        $oneShared = PublicationFactory::new([
            'author'              => $author,
            'publicationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2024-06-01T00:00:00+00:00'),
            'shortDescription'    => 'One shared',
            'slug'                => 'one-shared',
            'status'              => Publication::STATUS_ONLINE,
            'subCategory'         => $subNews,
            'title'               => 'One Shared Tag',
            'type'                => Publication::TYPE_TEXT,
            'viewCache'           => ViewCacheFactory::new(['count' => 0])->create(),
            'tags'                => new ArrayCollection([$tagMetal, $tagBlues]),
        ])->create();

        // No shared tag but same subcategory — fills the 3rd slot via fallback
        $categoryFallback = PublicationFactory::new([
            'author'              => $author,
            'publicationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2023-01-01T00:00:00+00:00'),
            'shortDescription'    => 'Category fallback',
            'slug'                => 'category-fallback',
            'status'              => Publication::STATUS_ONLINE,
            'subCategory'         => $subChronique,
            'title'               => 'Category Fallback',
            'type'                => Publication::TYPE_TEXT,
            'viewCache'           => ViewCacheFactory::new(['count' => 0])->create(),
            'tags'                => new ArrayCollection([$tagBlues]),
        ])->create();

        // No shared tag and different subcategory — must NOT appear
        PublicationFactory::new([
            'author'              => $author,
            'publicationDatetime' => \DateTime::createFromFormat(\DateTimeInterface::ATOM, '2022-01-01T00:00:00+00:00'),
            'shortDescription'    => 'Excluded',
            'slug'                => 'excluded',
            'status'              => Publication::STATUS_ONLINE,
            'subCategory'         => $subNews,
            'title'               => 'Excluded',
            'type'                => Publication::TYPE_TEXT,
            'viewCache'           => ViewCacheFactory::new(['count' => 0])->create(),
            'tags'                => new ArrayCollection([$tagBlues]),
        ])->create();

        $this->client->request('GET', '/api/publications/current/related');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context'   => '/api/contexts/Publication',
            '@id'        => '/api/publications/current/related',
            '@type'      => 'Collection',
            'member'     => [
                [
                    '@id'                  => '/api/publications/two-shared',
                    '@type'                => 'Publication',
                    'id'                   => $twoShared->id,
                    'title'                => 'Two Shared Tags',
                    'sub_category'         => [
                        '@type'      => 'SubCategory',
                        'id'         => $subNews->id,
                        'title'      => 'News',
                        'slug'       => 'news',
                        'is_course'  => false,
                    ],
                    'author'               => [
                        '@type'    => 'Author',
                        'username' => 'user_admin',
                        'display_name' => 'user_admin',
                        'deletion_datetime' => null,
                    ],
                    'slug'                 => 'two-shared',
                    'publication_datetime' => '2024-01-01T00:00:00+00:00',
                    'cover'                => null,
                    'description'          => 'Two shared',
                    'language'             => 'fr',
                    'upvotes'              => 0,
                    'downvotes'            => 0,
                    'user_vote'            => null,
                ],
                [
                    '@id'                  => '/api/publications/one-shared',
                    '@type'                => 'Publication',
                    'id'                   => $oneShared->id,
                    'title'                => 'One Shared Tag',
                    'sub_category'         => [
                        '@type'      => 'SubCategory',
                        'id'         => $subNews->id,
                        'title'      => 'News',
                        'slug'       => 'news',
                        'is_course'  => false,
                    ],
                    'author'               => [
                        '@type'    => 'Author',
                        'username' => 'user_admin',
                        'display_name' => 'user_admin',
                        'deletion_datetime' => null,
                    ],
                    'slug'                 => 'one-shared',
                    'publication_datetime' => '2024-06-01T00:00:00+00:00',
                    'cover'                => null,
                    'description'          => 'One shared',
                    'language'             => 'fr',
                    'upvotes'              => 0,
                    'downvotes'            => 0,
                    'user_vote'            => null,
                ],
                [
                    '@id'                  => '/api/publications/category-fallback',
                    '@type'                => 'Publication',
                    'id'                   => $categoryFallback->id,
                    'title'                => 'Category Fallback',
                    'sub_category'         => [
                        '@type'      => 'SubCategory',
                        'id'         => $subChronique->id,
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
                    'slug'                 => 'category-fallback',
                    'publication_datetime' => '2023-01-01T00:00:00+00:00',
                    'cover'                => null,
                    'description'          => 'Category fallback',
                    'language'             => 'fr',
                    'upvotes'              => 0,
                    'downvotes'            => 0,
                    'user_vote'            => null,
                ],
            ],
            'totalItems' => 3,
        ]);
    }

    public function test_get_related_publications_with_nonexistent_slug(): void
    {
        $this->client->request('GET', '/api/publications/nonexistent-slug/related');
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context'   => '/api/contexts/Publication',
            '@id'        => '/api/publications/nonexistent-slug/related',
            '@type'      => 'Collection',
            'member'     => [],
            'totalItems' => 0,
        ]);
    }

    public function test_get_related_publications_in_english(): void
    {
        $news = PublicationSubCategoryFactory::new()->asNews()->create();
        $author = UserFactory::new()->asAdminUser()->create();
        $current = PublicationFactory::new([
            'author' => $author, 'subCategory' => $news, 'status' => Publication::STATUS_ONLINE,
            'type' => Publication::TYPE_TEXT, 'language' => 'en', 'slug' => 'current-article',
            'publicationDatetime' => new \DateTime('2022-01-04 10:00:00'),
        ])->create();
        // The newest of the rest, so leaving it out is the filter's doing, not the limit's.
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

        $this->client->request('GET', '/api/publications/current-article/related', [], [], ['HTTP_X_LOCALE' => 'en']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Publication',
            '@id' => '/api/publications/current-article/related',
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

    /**
     * One English publication shares the tag, so the category fallback fills the rest and must not list it
     * twice. A French publication on each path is newer than the English one it competes with.
     */
    public function test_get_related_publications_in_english_by_tag_then_by_category(): void
    {
        $news = PublicationSubCategoryFactory::new()->asNews()->create();
        $author = UserFactory::new()->asAdminUser()->create();
        $metal = TagFactory::new(['label' => 'Metal', 'slug' => 'metal'])->create();
        $publication = static fn (array $attributes): Publication => PublicationFactory::new([
            'author' => $author, 'subCategory' => $news, 'status' => Publication::STATUS_ONLINE, ...$attributes,
        ])->create();

        $publication([
            'type' => Publication::TYPE_TEXT, 'language' => 'en', 'slug' => 'current-article',
            'publicationDatetime' => new \DateTime('2025-01-01 10:00:00'), 'tags' => new ArrayCollection([$metal]),
        ]);
        $publication([
            'type' => Publication::TYPE_TEXT, 'language' => 'fr', 'slug' => 'une-critique-metal',
            'publicationDatetime' => new \DateTime('2024-06-01 10:00:00'), 'tags' => new ArrayCollection([$metal]),
        ]);
        $englishTagged = $publication([
            'type' => Publication::TYPE_TEXT, 'language' => 'en', 'slug' => 'a-metal-review', 'title' => 'A metal review',
            'shortDescription' => 'Shares the tag', 'publicationDatetime' => new \DateTime('2024-01-01 10:00:00'),
            'tags' => new ArrayCollection([$metal]),
        ]);
        $publication([
            'type' => Publication::TYPE_TEXT, 'language' => 'fr', 'slug' => 'un-autre-article',
            'publicationDatetime' => new \DateTime('2024-05-01 10:00:00'),
        ]);
        $englishUntagged = $publication([
            'type' => Publication::TYPE_TEXT, 'language' => 'en', 'slug' => 'another-article', 'title' => 'Another article',
            'shortDescription' => 'Same category', 'publicationDatetime' => new \DateTime('2023-01-01 10:00:00'),
        ]);
        $video = $publication([
            'type' => Publication::TYPE_VIDEO, 'slug' => 'v-une-video', 'title' => 'Une video',
            'publicationDatetime' => new \DateTime('2022-01-01 10:00:00'),
        ]);

        $this->client->request('GET', '/api/publications/current-article/related', [], [], ['HTTP_X_LOCALE' => 'en']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Publication',
            '@id' => '/api/publications/current-article/related',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/publications/a-metal-review',
                    '@type' => 'Publication',
                    'id' => $englishTagged->id,
                    'title' => 'A metal review',
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
                    'slug' => 'a-metal-review',
                    'publication_datetime' => '2024-01-01T10:00:00+00:00',
                    'cover' => null,
                    'description' => 'Shares the tag',
                    'language' => 'en',
                    'upvotes' => 0,
                    'downvotes' => 0,
                    'user_vote' => null,
                ],
                [
                    '@id' => '/api/publications/another-article',
                    '@type' => 'Publication',
                    'id' => $englishUntagged->id,
                    'title' => 'Another article',
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
                    'slug' => 'another-article',
                    'publication_datetime' => '2023-01-01T10:00:00+00:00',
                    'cover' => null,
                    'description' => 'Same category',
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
            'totalItems' => 3,
        ]);
    }
}
