<?php

declare(strict_types=1);

namespace App\Tests\Api\Profile;

use App\Entity\Musician\MusicianAnnounce;
use App\Entity\Publication;
use App\Entity\User;
use App\Enum\Musician\MediaPlatform;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Attribute\InstrumentFactory;
use App\Tests\Factory\Attribute\StyleFactory;
use App\Tests\Factory\Musician\MusicianProfileFactory;
use App\Tests\Factory\Musician\MusicianProfileMediaFactory;
use App\Tests\Factory\Profile\ProfilePostFactory;
use App\Tests\Factory\Publication\PublicationFactory;
use App\Tests\Factory\Publication\PublicationSubCategoryFactory;
use App\Tests\Factory\Teacher\TeacherProfileFactory;
use App\Tests\Factory\Teacher\TeacherProfileMediaFactory;
use App\Tests\Factory\User\MusicianAnnounceFactory;
use App\Tests\Factory\User\UserBlockFactory;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/** What a member did elsewhere on MusicAll, between their posts on the wall (#1316). */
#[ResetDatabase]
class ProfileWallActivityTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_the_wall_mixes_posts_and_activity_by_date(): void
    {
        $owner = $this->member('julien');
        $post = ProfilePostFactory::new()->create([
            'author' => $owner,
            'content' => 'Le clip est en ligne',
            'creationDatetime' => new \DateTimeImmutable('2026-10-06T10:00:00+00:00'),
        ]);
        $announce = MusicianAnnounceFactory::new()->create([
            'author' => $owner,
            'type' => MusicianAnnounce::TYPE_MUSICIAN,
            'instrument' => InstrumentFactory::new()->asDrum(),
            'styles' => [StyleFactory::new()->asRock()],
            'locationName' => 'Bruxelles',
            'creationDatetime' => new \DateTime('2026-10-05T10:00:00+00:00'),
        ]);
        $articles = PublicationSubCategoryFactory::new()->asArticle()->create();
        $article = PublicationFactory::new()->create([
            'author' => $owner,
            'subCategory' => $articles,
            'title' => 'Ma chronique',
            'shortDescription' => 'Trois albums de la rentrée',
            'slug' => 'ma-chronique',
            'status' => Publication::STATUS_ONLINE,
            'type' => Publication::TYPE_TEXT,
            'language' => 'fr',
            'publicationDatetime' => new \DateTime('2026-10-04T10:00:00+00:00'),
        ]);
        $course = PublicationFactory::new()->create([
            'author' => $owner,
            'subCategory' => PublicationSubCategoryFactory::new()->asCourse(),
            'title' => 'Caler sa main droite',
            'shortDescription' => 'Les arpèges sans fatigue',
            'slug' => 'caler-sa-main-droite',
            'status' => Publication::STATUS_ONLINE,
            'type' => Publication::TYPE_TEXT,
            'language' => 'fr',
            'publicationDatetime' => new \DateTime('2026-10-03T10:00:00+00:00'),
        ]);
        $musicianMedia = MusicianProfileMediaFactory::new()->create([
            'musicianProfile' => MusicianProfileFactory::new(['user' => $owner]),
            'title' => 'Live au Botanique',
            'creationDatetime' => new \DateTimeImmutable('2026-10-02T10:00:00+00:00'),
        ]);
        $teacherMedia = TeacherProfileMediaFactory::new()->create([
            'teacherProfile' => TeacherProfileFactory::new(['user' => $owner]),
            'platform' => MediaPlatform::SOUNDCLOUD,
            'url' => 'https://soundcloud.com/julien/arpeges',
            'embedId' => 'julien/arpeges',
            'title' => 'Arpèges',
            'creationDatetime' => new \DateTimeImmutable('2026-10-01T10:00:00+00:00'),
        ]);
        // Not on the wall: a draft, a publication waiting for review, one in another language, someone else's announce.
        foreach ([[Publication::STATUS_DRAFT, 'fr'], [Publication::STATUS_PENDING, 'fr'], [Publication::STATUS_ONLINE, 'en']] as $index => [$status, $language]) {
            PublicationFactory::new()->create([
                'author' => $owner,
                'subCategory' => $articles,
                'slug' => 'hidden-' . $index,
                'status' => $status,
                'type' => Publication::TYPE_TEXT,
                'language' => $language,
                'publicationDatetime' => new \DateTime('2026-10-07T10:00:00+00:00'),
            ]);
        }
        MusicianAnnounceFactory::new()->create(['author' => $this->member('chloe')]);

        $this->client->request('GET', '/api/user/profile/julien/wall');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProfileWallItem',
            '@id' => '/api/user/profile/julien/wall',
            '@type' => 'Collection',
            'totalItems' => 6,
            'member' => [
                [
                    '@id' => '/api/profile_wall_items/' . self::cursor('post', '2026-10-06T10:00:00+00:00', $post->id),
                    '@type' => 'ProfileWallItem',
                    'cursor' => self::cursor('post', '2026-10-06T10:00:00+00:00', $post->id),
                    'type' => 'post',
                    'date' => '2026-10-06T10:00:00+00:00',
                    'post' => [
                        '@type' => 'ProfilePost',
                        'id' => $post->id,
                        'author' => ['@type' => 'ProfilePostAuthor', 'username' => 'julien', 'display_name' => 'julien'],
                        'content' => 'Le clip est en ligne',
                        'photos' => [],
                        'is_pinned' => false,
                        'creation_datetime' => '2026-10-06T10:00:00+00:00',
                        'can_edit' => false,
                        'like_count' => 0,
                        'has_liked' => false,
                        'comment_count' => 0,
                    ],
                ],
                [
                    '@id' => '/api/profile_wall_items/' . self::cursor('announce', '2026-10-05T10:00:00+00:00', $announce->id),
                    '@type' => 'ProfileWallItem',
                    'cursor' => self::cursor('announce', '2026-10-05T10:00:00+00:00', $announce->id),
                    'type' => 'announce',
                    'date' => '2026-10-05T10:00:00+00:00',
                    'announce' => [
                        '@type' => 'PublicProfileAnnounce',
                        'id' => $announce->id,
                        'creation_datetime' => '2026-10-05T10:00:00+00:00',
                        'type' => MusicianAnnounce::TYPE_MUSICIAN,
                        'instrument_name' => 'Batteur',
                        'location_name' => 'Bruxelles',
                        'styles' => ['Rock'],
                    ],
                ],
                [
                    '@id' => '/api/profile_wall_items/' . self::cursor('publication', '2026-10-04T10:00:00+00:00', $article->id),
                    '@type' => 'ProfileWallItem',
                    'cursor' => self::cursor('publication', '2026-10-04T10:00:00+00:00', $article->id),
                    'type' => 'publication',
                    'date' => '2026-10-04T10:00:00+00:00',
                    'publication' => [
                        '@type' => 'Publication',
                        'id' => $article->id,
                        'title' => 'Ma chronique',
                        'sub_category' => ['@type' => 'SubCategory', 'id' => $articles->id, 'title' => 'Articles', 'slug' => 'articles', 'is_course' => false],
                        'author' => ['@type' => 'Author', 'username' => 'julien', 'display_name' => 'julien'],
                        'slug' => 'ma-chronique',
                        'publication_datetime' => '2026-10-04T10:00:00+00:00',
                        'description' => 'Trois albums de la rentrée',
                        'language' => 'fr',
                        'upvotes' => 0,
                        'downvotes' => 0,
                    ],
                ],
                [
                    '@id' => '/api/profile_wall_items/' . self::cursor('publication', '2026-10-03T10:00:00+00:00', $course->id),
                    '@type' => 'ProfileWallItem',
                    'cursor' => self::cursor('publication', '2026-10-03T10:00:00+00:00', $course->id),
                    'type' => 'course',
                    'date' => '2026-10-03T10:00:00+00:00',
                    'publication' => [
                        '@type' => 'Publication',
                        'id' => $course->id,
                        'title' => 'Caler sa main droite',
                        'sub_category' => ['@type' => 'SubCategory', 'id' => $course->subCategory->id, 'title' => 'Cours', 'slug' => 'cours', 'is_course' => true],
                        'author' => ['@type' => 'Author', 'username' => 'julien', 'display_name' => 'julien'],
                        'slug' => 'caler-sa-main-droite',
                        'publication_datetime' => '2026-10-03T10:00:00+00:00',
                        'description' => 'Les arpèges sans fatigue',
                        'language' => 'fr',
                        'upvotes' => 0,
                        'downvotes' => 0,
                    ],
                ],
                [
                    '@id' => '/api/profile_wall_items/' . self::cursor('musician_media', '2026-10-02T10:00:00+00:00', $musicianMedia->id),
                    '@type' => 'ProfileWallItem',
                    'cursor' => self::cursor('musician_media', '2026-10-02T10:00:00+00:00', $musicianMedia->id),
                    'type' => 'media',
                    'date' => '2026-10-02T10:00:00+00:00',
                    'media' => [
                        '@type' => 'ProfileWallMedia',
                        'id' => $musicianMedia->id,
                        'profile' => 'musician',
                        'platform' => 'youtube',
                        'platform_label' => 'YouTube',
                        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                        'embed_id' => 'dQw4w9WgXcQ',
                        'title' => 'Live au Botanique',
                    ],
                ],
                [
                    '@id' => '/api/profile_wall_items/' . self::cursor('teacher_media', '2026-10-01T10:00:00+00:00', $teacherMedia->id),
                    '@type' => 'ProfileWallItem',
                    'cursor' => self::cursor('teacher_media', '2026-10-01T10:00:00+00:00', $teacherMedia->id),
                    'type' => 'media',
                    'date' => '2026-10-01T10:00:00+00:00',
                    'media' => [
                        '@type' => 'ProfileWallMedia',
                        'id' => $teacherMedia->id,
                        'profile' => 'teacher',
                        'platform' => 'soundcloud',
                        'platform_label' => 'SoundCloud',
                        'url' => 'https://soundcloud.com/julien/arpeges',
                        'embed_id' => 'julien/arpeges',
                        'title' => 'Arpèges',
                    ],
                ],
            ],
        ]);
    }

    public function test_a_page_ends_inside_a_second_and_the_next_one_goes_on_from_there(): void
    {
        $owner = $this->member('julien');
        $second = new \DateTimeImmutable('2026-10-05T10:00:00+00:00');
        $posts = [];
        $announces = [];
        foreach (range(1, 6) as $index) {
            $posts[] = ProfilePostFactory::new()->create(['author' => $owner, 'content' => 'Post ' . $index, 'creationDatetime' => $second]);
            $announces[] = MusicianAnnounceFactory::new()->create(['author' => $owner, 'creationDatetime' => \DateTime::createFromImmutable($second)]);
        }
        // An older line, so the second page shows the order goes on past the shared second.
        $older = MusicianProfileMediaFactory::new()->create([
            'musicianProfile' => MusicianProfileFactory::new(['user' => $owner]),
            'creationDatetime' => new \DateTimeImmutable('2026-10-04T10:00:00+00:00'),
        ]);
        // Within a second, a post comes before an announce, and each kind goes by id, newest first.
        $byIdDesc = static fn (object $a, object $b): int => strcmp((string) $b->id, (string) $a->id);
        usort($posts, $byIdDesc);
        usort($announces, $byIdDesc);

        $postItem = static fn (object $post): array => [
            '@id' => '/api/profile_wall_items/' . self::cursor('post', '2026-10-05T10:00:00+00:00', $post->id),
            '@type' => 'ProfileWallItem',
            'cursor' => self::cursor('post', '2026-10-05T10:00:00+00:00', $post->id),
            'type' => 'post',
            'date' => '2026-10-05T10:00:00+00:00',
            'post' => [
                '@type' => 'ProfilePost',
                'id' => $post->id,
                'author' => ['@type' => 'ProfilePostAuthor', 'username' => 'julien', 'display_name' => 'julien'],
                'content' => $post->content,
                'photos' => [],
                'is_pinned' => false,
                'creation_datetime' => '2026-10-05T10:00:00+00:00',
                'can_edit' => false,
                'like_count' => 0,
                'has_liked' => false,
                'comment_count' => 0,
            ],
        ];
        $announceItem = static fn (object $announce): array => [
            '@id' => '/api/profile_wall_items/' . self::cursor('announce', '2026-10-05T10:00:00+00:00', $announce->id),
            '@type' => 'ProfileWallItem',
            'cursor' => self::cursor('announce', '2026-10-05T10:00:00+00:00', $announce->id),
            'type' => 'announce',
            'date' => '2026-10-05T10:00:00+00:00',
            'announce' => [
                '@type' => 'PublicProfileAnnounce',
                'id' => $announce->id,
                'creation_datetime' => '2026-10-05T10:00:00+00:00',
                'type' => $announce->type,
                'instrument_name' => $announce->instrument->musicianName,
                'location_name' => $announce->locationName,
                'styles' => [],
            ],
        ];
        // The first page is full, so the next one starts after its last line: the fourth announce.
        $cursor = self::cursor('announce', '2026-10-05T10:00:00+00:00', $announces[3]->id);

        $this->client->request('GET', '/api/user/profile/julien/wall');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProfileWallItem',
            '@id' => '/api/user/profile/julien/wall',
            '@type' => 'Collection',
            'totalItems' => 10,
            'member' => [...array_map($postItem, $posts), ...array_map($announceItem, array_slice($announces, 0, 4))],
        ]);

        $this->client->request('GET', '/api/user/profile/julien/wall?before=' . $cursor);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProfileWallItem',
            '@id' => '/api/user/profile/julien/wall',
            '@type' => 'Collection',
            'totalItems' => 3,
            'member' => [
                ...array_map($announceItem, array_slice($announces, 4)),
                [
                    '@id' => '/api/profile_wall_items/' . self::cursor('musician_media', '2026-10-04T10:00:00+00:00', $older->id),
                    '@type' => 'ProfileWallItem',
                    'cursor' => self::cursor('musician_media', '2026-10-04T10:00:00+00:00', $older->id),
                    'type' => 'media',
                    'date' => '2026-10-04T10:00:00+00:00',
                    'media' => [
                        '@type' => 'ProfileWallMedia',
                        'id' => $older->id,
                        'profile' => 'musician',
                        'platform' => 'youtube',
                        'platform_label' => 'YouTube',
                        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                        'embed_id' => 'dQw4w9WgXcQ',
                        'title' => $older->title,
                    ],
                ],
            ],
            'view' => [
                '@id' => '/api/user/profile/julien/wall?before=' . $cursor,
                '@type' => 'PartialCollectionView',
            ],
        ]);
    }

    public function test_an_english_reader_sees_the_english_text_and_everyone_sees_a_video(): void
    {
        $owner = $this->member('julien');
        $articles = PublicationSubCategoryFactory::new()->asArticle()->create();
        PublicationFactory::new()->create([
            'author' => $owner,
            'subCategory' => $articles,
            'slug' => 'ma-chronique',
            'status' => Publication::STATUS_ONLINE,
            'type' => Publication::TYPE_TEXT,
            'language' => 'fr',
            'publicationDatetime' => new \DateTime('2026-10-04T10:00:00+00:00'),
        ]);
        $english = PublicationFactory::new()->create([
            'author' => $owner,
            'subCategory' => $articles,
            'title' => 'My review',
            'shortDescription' => 'Three autumn records',
            'slug' => 'my-review',
            'status' => Publication::STATUS_ONLINE,
            'type' => Publication::TYPE_TEXT,
            'language' => 'en',
            'publicationDatetime' => new \DateTime('2026-10-03T10:00:00+00:00'),
        ]);
        // A video has no language: every listing shows it.
        $video = PublicationFactory::new()->create([
            'author' => $owner,
            'subCategory' => $articles,
            'title' => 'Live au Botanique',
            'slug' => 'live-au-botanique',
            'status' => Publication::STATUS_ONLINE,
            'type' => Publication::TYPE_VIDEO,
            'language' => null,
            'publicationDatetime' => new \DateTime('2026-10-02T10:00:00+00:00'),
        ]);

        $this->client->request('GET', '/api/user/profile/julien/wall', server: ['HTTP_X_LOCALE' => 'en']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProfileWallItem',
            '@id' => '/api/user/profile/julien/wall',
            '@type' => 'Collection',
            'totalItems' => 2,
            'member' => [
                [
                    '@id' => '/api/profile_wall_items/' . self::cursor('publication', '2026-10-03T10:00:00+00:00', $english->id),
                    '@type' => 'ProfileWallItem',
                    'cursor' => self::cursor('publication', '2026-10-03T10:00:00+00:00', $english->id),
                    'type' => 'publication',
                    'date' => '2026-10-03T10:00:00+00:00',
                    'publication' => [
                        '@type' => 'Publication',
                        'id' => $english->id,
                        'title' => 'My review',
                        'sub_category' => ['@type' => 'SubCategory', 'id' => $articles->id, 'title' => 'Articles', 'slug' => 'articles', 'is_course' => false],
                        'author' => ['@type' => 'Author', 'username' => 'julien', 'display_name' => 'julien'],
                        'slug' => 'my-review',
                        'publication_datetime' => '2026-10-03T10:00:00+00:00',
                        'description' => 'Three autumn records',
                        'language' => 'en',
                        'upvotes' => 0,
                        'downvotes' => 0,
                    ],
                ],
                [
                    '@id' => '/api/profile_wall_items/' . self::cursor('publication', '2026-10-02T10:00:00+00:00', $video->id),
                    '@type' => 'ProfileWallItem',
                    'cursor' => self::cursor('publication', '2026-10-02T10:00:00+00:00', $video->id),
                    'type' => 'publication',
                    'date' => '2026-10-02T10:00:00+00:00',
                    'publication' => [
                        '@type' => 'Publication',
                        'id' => $video->id,
                        'title' => 'Live au Botanique',
                        'sub_category' => ['@type' => 'SubCategory', 'id' => $articles->id, 'title' => 'Articles', 'slug' => 'articles', 'is_course' => false],
                        'author' => ['@type' => 'Author', 'username' => 'julien', 'display_name' => 'julien'],
                        'slug' => 'live-au-botanique',
                        'publication_datetime' => '2026-10-02T10:00:00+00:00',
                        'upvotes' => 0,
                        'downvotes' => 0,
                    ],
                ],
            ],
        ]);
    }

    public function test_a_page_ending_on_a_publication_goes_on_to_a_media_of_the_same_second(): void
    {
        $owner = $this->member('julien');
        $second = new \DateTimeImmutable('2026-10-05T10:00:00+00:00');
        $articles = PublicationSubCategoryFactory::new()->asArticle()->create();
        $publications = [];
        foreach (range(1, 11) as $index) {
            $publications[] = PublicationFactory::new()->create([
                'author' => $owner,
                'subCategory' => $articles,
                'slug' => 'publication-' . $index,
                'status' => Publication::STATUS_ONLINE,
                'type' => Publication::TYPE_TEXT,
                'language' => 'fr',
                'publicationDatetime' => \DateTime::createFromImmutable($second),
            ]);
        }
        // Ranked below a publication, in the same second: it follows the last one.
        $media = TeacherProfileMediaFactory::new()->create([
            'teacherProfile' => TeacherProfileFactory::new(['user' => $owner]),
            'title' => 'Arpèges',
            'creationDatetime' => $second,
        ]);
        // Ids 11 down to 2 fill the first page: it ends on publication 2.
        $cursor = 'publication_' . $second->getTimestamp() . '_' . $publications[1]->id;

        $this->client->request('GET', '/api/user/profile/julien/wall?before=' . $cursor);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProfileWallItem',
            '@id' => '/api/user/profile/julien/wall',
            '@type' => 'Collection',
            'totalItems' => 2,
            'member' => [
                [
                    '@id' => '/api/profile_wall_items/' . self::cursor('publication', '2026-10-05T10:00:00+00:00', $publications[0]->id),
                    '@type' => 'ProfileWallItem',
                    'cursor' => self::cursor('publication', '2026-10-05T10:00:00+00:00', $publications[0]->id),
                    'type' => 'publication',
                    'date' => '2026-10-05T10:00:00+00:00',
                    'publication' => [
                        '@type' => 'Publication',
                        'id' => $publications[0]->id,
                        'title' => $publications[0]->title,
                        'sub_category' => ['@type' => 'SubCategory', 'id' => $articles->id, 'title' => 'Articles', 'slug' => 'articles', 'is_course' => false],
                        'author' => ['@type' => 'Author', 'username' => 'julien', 'display_name' => 'julien'],
                        'slug' => 'publication-1',
                        'publication_datetime' => '2026-10-05T10:00:00+00:00',
                        'description' => $publications[0]->getDescription(),
                        'language' => 'fr',
                        'upvotes' => 0,
                        'downvotes' => 0,
                    ],
                ],
                [
                    '@id' => '/api/profile_wall_items/' . self::cursor('teacher_media', '2026-10-05T10:00:00+00:00', $media->id),
                    '@type' => 'ProfileWallItem',
                    'cursor' => self::cursor('teacher_media', '2026-10-05T10:00:00+00:00', $media->id),
                    'type' => 'media',
                    'date' => '2026-10-05T10:00:00+00:00',
                    'media' => [
                        '@type' => 'ProfileWallMedia',
                        'id' => $media->id,
                        'profile' => 'teacher',
                        'platform' => 'youtube',
                        'platform_label' => 'YouTube',
                        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                        'embed_id' => 'dQw4w9WgXcQ',
                        'title' => 'Arpèges',
                    ],
                ],
            ],
            'view' => [
                '@id' => '/api/user/profile/julien/wall?before=' . $cursor,
                '@type' => 'PartialCollectionView',
            ],
        ]);
    }

    public function test_activity_stays_behind_a_block(): void
    {
        $owner = $this->member('julien');
        $viewer = $this->member('chloe');
        MusicianAnnounceFactory::new()->create(['author' => $owner]);
        UserBlockFactory::new()->create(['blocker' => $viewer, 'blocked' => $owner]);

        $this->client->loginUser($viewer);
        $this->client->request('GET', '/api/user/profile/julien/wall');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProfileWallItem',
            '@id' => '/api/user/profile/julien/wall',
            '@type' => 'Collection',
            'totalItems' => 0,
            'member' => [],
        ]);
    }

    /** A line's place on the wall, as its `cursor` gives it. */
    private static function cursor(string $kind, string $date, string|int $id): string
    {
        return $kind . '_' . new \DateTimeImmutable($date)->getTimestamp() . '_' . $id;
    }

    private function member(string $username, bool $isPublic = true): User
    {
        $user = UserFactory::new()->asBaseUser()->create(['username' => $username, 'email' => $username . '@test.com']);
        $user->profile->isPublic = $isPublic;
        \Zenstruck\Foundry\Persistence\save($user);

        return $user;
    }
}
