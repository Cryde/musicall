<?php

declare(strict_types=1);

namespace App\Tests\Api\Profile;

use App\Entity\Musician\MusicianAnnounce;
use App\Entity\Service\Service;
use App\Entity\User;
use App\Enum\Service\OfferUnit;
use App\Enum\Service\ServiceStatus;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Attribute\InstrumentFactory;
use App\Tests\Factory\Profile\ProfilePostFactory;
use App\Tests\Factory\Service\ServiceFactory;
use App\Tests\Factory\Service\ServiceOfferFactory;
use App\Tests\Factory\Service\ServicePlaceFactory;
use App\Tests\Factory\User\MusicianAnnounceFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/** « a publié un service » (#1415): once per service, dated the first time it went online. */
#[ResetDatabase]
class ProfileWallServiceTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;
    use ClockSensitiveTrait;

    public function test_a_service_is_on_the_wall_from_its_first_publication(): void
    {
        $owner = $this->member('julien');
        $post = ProfilePostFactory::new()->create([
            'author' => $owner,
            'content' => 'Le studio est prêt',
            'creationDatetime' => new \DateTimeImmutable('2026-10-06T10:00:00+00:00'),
        ]);
        // Created as a draft a month before it went online: the line is dated when it did.
        $mixing = ServiceFactory::new()->soundEngineering()->create([
            'user' => $owner,
            'headline' => 'Mixage et prise de son',
            'creationDatetime' => new \DateTimeImmutable('2026-09-01T10:00:00+00:00'),
            'firstPublicationDatetime' => new \DateTimeImmutable('2026-10-05T10:00:00+00:00'),
        ]);
        ServiceOfferFactory::new()->create(['service' => $mixing, 'unit' => OfferUnit::Track, 'quantity' => null, 'price' => 8000, 'title' => "Mixage d'un titre"]);
        // Online once, offline since: its line leaves the wall with it.
        ServiceFactory::new()->teaching()->create([
            'user' => $owner,
            'status' => ServiceStatus::Draft,
            'firstPublicationDatetime' => new \DateTimeImmutable('2026-10-04T10:00:00+00:00'),
        ]);
        $announce = MusicianAnnounceFactory::new()->create([
            'author' => $owner,
            'type' => MusicianAnnounce::TYPE_MUSICIAN,
            'instrument' => InstrumentFactory::new()->asDrum(),
            'locationName' => 'Bruxelles',
            'creationDatetime' => new \DateTime('2026-10-03T10:00:00+00:00'),
        ]);
        ServiceFactory::new()->soundEngineering()->create([
            'user' => $this->member('chloe'),
            'firstPublicationDatetime' => new \DateTimeImmutable('2026-10-05T12:00:00+00:00'),
        ]);

        $this->client->request('GET', '/api/user/profile/julien/wall');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProfileWallItem',
            '@id' => '/api/user/profile/julien/wall',
            '@type' => 'Collection',
            'totalItems' => 3,
            'member' => [
                [
                    '@id' => '/api/profile_wall_items/' . self::cursor('post', '2026-10-06T10:00:00+00:00', (string) $post->id),
                    '@type' => 'ProfileWallItem',
                    'cursor' => self::cursor('post', '2026-10-06T10:00:00+00:00', (string) $post->id),
                    'type' => 'post',
                    'date' => '2026-10-06T10:00:00+00:00',
                    'post' => [
                        '@type' => 'ProfilePost',
                        'id' => $post->id,
                        'author' => ['@type' => 'ProfilePostAuthor', 'username' => 'julien', 'display_name' => 'julien'],
                        'content' => 'Le studio est prêt',
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
                    '@id' => '/api/profile_wall_items/' . self::cursor('service', '2026-10-05T10:00:00+00:00', (string) $mixing->id),
                    '@type' => 'ProfileWallItem',
                    'cursor' => self::cursor('service', '2026-10-05T10:00:00+00:00', (string) $mixing->id),
                    'type' => 'service',
                    'date' => '2026-10-05T10:00:00+00:00',
                    'service' => [
                        '@type' => 'PublicProfileService',
                        'id' => $mixing->id,
                        'kind' => 'sound_engineering',
                        'status' => 'published',
                        'headline' => 'Mixage et prise de son',
                        'url' => 'http://localhost/u/julien/sound-engineer',
                        'price' => 8000,
                        'price_unit' => 'track',
                    ],
                ],
                [
                    '@id' => '/api/profile_wall_items/' . self::cursor('announce', '2026-10-03T10:00:00+00:00', (string) $announce->id),
                    '@type' => 'ProfileWallItem',
                    'cursor' => self::cursor('announce', '2026-10-03T10:00:00+00:00', (string) $announce->id),
                    'type' => 'announce',
                    'date' => '2026-10-03T10:00:00+00:00',
                    'announce' => [
                        '@type' => 'PublicProfileAnnounce',
                        'id' => $announce->id,
                        'creation_datetime' => '2026-10-03T10:00:00+00:00',
                        'type' => MusicianAnnounce::TYPE_MUSICIAN,
                        'instrument_name' => 'Batteur',
                        'location_name' => 'Bruxelles',
                        'styles' => [],
                    ],
                ],
            ],
        ]);
    }

    public function test_the_services_filter_shows_only_the_services(): void
    {
        $owner = $this->member('julien');
        ProfilePostFactory::new()->create(['author' => $owner, 'creationDatetime' => new \DateTimeImmutable('2026-10-06T10:00:00+00:00')]);
        $teaching = ServiceFactory::new()->teaching()->create([
            'user' => $owner,
            'headline' => 'Cours de guitare',
            'firstPublicationDatetime' => new \DateTimeImmutable('2026-10-02T10:00:00+00:00'),
        ]);

        $this->client->request('GET', '/api/user/profile/julien/wall?type=service');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProfileWallItem',
            '@id' => '/api/user/profile/julien/wall',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                [
                    '@id' => '/api/profile_wall_items/' . self::cursor('service', '2026-10-02T10:00:00+00:00', (string) $teaching->id),
                    '@type' => 'ProfileWallItem',
                    'cursor' => self::cursor('service', '2026-10-02T10:00:00+00:00', (string) $teaching->id),
                    'type' => 'service',
                    'date' => '2026-10-02T10:00:00+00:00',
                    'service' => [
                        '@type' => 'PublicProfileService',
                        'id' => $teaching->id,
                        'kind' => 'teaching',
                        'status' => 'published',
                        'headline' => 'Cours de guitare',
                        'url' => 'http://localhost/u/julien/teacher',
                    ],
                ],
            ],
            'view' => [
                '@id' => '/api/user/profile/julien/wall?type=service',
                '@type' => 'PartialCollectionView',
            ],
        ]);
    }

    /** A page ending on a service goes on from it: the cursor names the service kind. */
    public function test_a_page_ending_on_a_service_goes_on_past_it(): void
    {
        $owner = $this->member('julien');
        $teaching = ServiceFactory::new()->teaching()->create([
            'user' => $owner,
            'firstPublicationDatetime' => new \DateTimeImmutable('2026-10-02T10:00:00+00:00'),
        ]);
        $mixing = ServiceFactory::new()->soundEngineering()->create([
            'user' => $owner,
            'firstPublicationDatetime' => new \DateTimeImmutable('2026-10-04T10:00:00+00:00'),
        ]);
        $cursor = self::cursor('service', '2026-10-04T10:00:00+00:00', (string) $mixing->id);

        $this->client->request('GET', '/api/user/profile/julien/wall?type=service&before=' . $cursor);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProfileWallItem',
            '@id' => '/api/user/profile/julien/wall',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                [
                    '@id' => '/api/profile_wall_items/' . self::cursor('service', '2026-10-02T10:00:00+00:00', (string) $teaching->id),
                    '@type' => 'ProfileWallItem',
                    'cursor' => self::cursor('service', '2026-10-02T10:00:00+00:00', (string) $teaching->id),
                    'type' => 'service',
                    'date' => '2026-10-02T10:00:00+00:00',
                    'service' => [
                        '@type' => 'PublicProfileService',
                        'id' => $teaching->id,
                        'kind' => 'teaching',
                        'status' => 'published',
                        'url' => 'http://localhost/u/julien/teacher',
                    ],
                ],
            ],
            'view' => [
                // Hydra writes the parameters back sorted by name.
                '@id' => '/api/user/profile/julien/wall?before=' . $cursor . '&type=service',
                '@type' => 'PartialCollectionView',
            ],
        ]);
    }

    /** Published by its member: its line is dated that moment, not its creation. The wall is public, so read without a sign-in. */
    public function test_publishing_a_draft_puts_it_on_the_wall_at_that_moment(): void
    {
        self::mockTime('2026-11-20T10:00:00+00:00');
        $owner = $this->member('julien');
        $mixing = $this->publishableMixing($owner, null);

        $this->client->loginUser($owner);
        $this->client->request('POST', '/api/user/services/' . $mixing->id . '/publish');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MyService',
            '@id' => '/api/my_services/' . $mixing->id,
            '@type' => 'MyService',
            'id' => $mixing->id,
            'kind' => 'sound_engineering',
            'headline' => 'Mixage et prise de son',
            'status' => 'published',
            'completeness' => 45,
            'missing_to_publish' => [],
        ]);

        $this->client->request('GET', '/api/user/profile/julien/wall?type=service');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals($this->mixingLine($mixing, '2026-11-20T10:00:00+00:00'));
    }

    /** Taken offline and back: the line it had comes back, at its first date, and only once. */
    public function test_publishing_again_keeps_the_first_line(): void
    {
        self::mockTime('2026-11-20T10:00:00+00:00');
        $owner = $this->member('julien');
        $mixing = $this->publishableMixing($owner, new \DateTimeImmutable('2026-10-05T10:00:00+00:00'));

        $this->client->loginUser($owner);
        $this->client->request('POST', '/api/user/services/' . $mixing->id . '/publish');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MyService',
            '@id' => '/api/my_services/' . $mixing->id,
            '@type' => 'MyService',
            'id' => $mixing->id,
            'kind' => 'sound_engineering',
            'headline' => 'Mixage et prise de son',
            'status' => 'published',
            'completeness' => 45,
            'missing_to_publish' => [],
        ]);

        $this->client->request('GET', '/api/user/profile/julien/wall?type=service');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals($this->mixingLine($mixing, '2026-10-05T10:00:00+00:00'));
    }

    /** Taken offline: the line leaves the wall at once, its date kept for when it comes back. */
    public function test_unpublishing_takes_the_line_off_the_wall(): void
    {
        $owner = $this->member('julien');
        $mixing = ServiceFactory::new()->soundEngineering()->create([
            'user' => $owner,
            'headline' => 'Mixage et prise de son',
            'firstPublicationDatetime' => new \DateTimeImmutable('2026-10-05T10:00:00+00:00'),
        ]);

        $this->client->loginUser($owner);
        $this->client->request('POST', '/api/user/services/' . $mixing->id . '/unpublish');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/MyService',
            '@id' => '/api/my_services/' . $mixing->id,
            '@type' => 'MyService',
            'id' => $mixing->id,
            'kind' => 'sound_engineering',
            'headline' => 'Mixage et prise de son',
            'status' => 'draft',
            'completeness' => 9,
            'missing_to_publish' => ['description', 'specialities', 'places', 'offers'],
        ]);

        $this->client->request('GET', '/api/user/profile/julien/wall?type=service');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ProfileWallItem',
            '@id' => '/api/user/profile/julien/wall',
            '@type' => 'Collection',
            'totalItems' => 0,
            'member' => [],
            'view' => [
                '@id' => '/api/user/profile/julien/wall?type=service',
                '@type' => 'PartialCollectionView',
            ],
        ]);
    }

    /** @return array<string, mixed> the wall, filtered on services, holding the mixing alone */
    private function mixingLine(Service $mixing, string $date): array
    {
        return [
            '@context' => '/api/contexts/ProfileWallItem',
            '@id' => '/api/user/profile/julien/wall',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                [
                    '@id' => '/api/profile_wall_items/' . self::cursor('service', $date, (string) $mixing->id),
                    '@type' => 'ProfileWallItem',
                    'cursor' => self::cursor('service', $date, (string) $mixing->id),
                    'type' => 'service',
                    'date' => $date,
                    'service' => [
                        '@type' => 'PublicProfileService',
                        'id' => $mixing->id,
                        'kind' => 'sound_engineering',
                        'status' => 'published',
                        'headline' => 'Mixage et prise de son',
                        'url' => 'http://localhost/u/julien/sound-engineer',
                        'price' => 8000,
                        'price_unit' => 'track',
                    ],
                ],
            ],
            'view' => [
                '@id' => '/api/user/profile/julien/wall?type=service',
                '@type' => 'PartialCollectionView',
            ],
        ];
    }

    /** A sound engineering draft with what its kind needs to go online, created long before. */
    private function publishableMixing(User $owner, ?\DateTimeImmutable $firstPublication): Service
    {
        $mixing = ServiceFactory::new()->soundEngineering()->create([
            'user' => $owner,
            'status' => ServiceStatus::Draft,
            'headline' => 'Mixage et prise de son',
            'description' => 'Huit ans de studio.',
            'specialities' => ['mixing'],
            'creationDatetime' => new \DateTimeImmutable('2024-01-15T10:00:00+00:00'),
            'firstPublicationDatetime' => $firstPublication,
        ]);
        ServicePlaceFactory::new()->remote()->create(['service' => $mixing]);
        ServiceOfferFactory::new()->create(['service' => $mixing, 'unit' => OfferUnit::Track, 'quantity' => null, 'price' => 8000, 'title' => "Mixage d'un titre"]);

        return $mixing;
    }

    private static function cursor(string $kind, string $date, string $id): string
    {
        return $kind . '_' . new \DateTimeImmutable($date)->getTimestamp() . '_' . $id;
    }

    private function member(string $username): User
    {
        return UserFactory::new()->asBaseUser()->create(['username' => $username, 'email' => $username . '@test.com']);
    }
}
