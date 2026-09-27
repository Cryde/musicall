<?php

declare(strict_types=1);

namespace App\Tests\Api\Musician\Match;

use App\Entity\Attribute\Instrument;
use App\Entity\Musician\MusicianAnnounce;
use App\Entity\User;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Attribute\InstrumentFactory;
use App\Tests\Factory\Attribute\StyleFactory;
use App\Tests\Factory\User\MusicianAnnounceFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class AnnounceMatchesGetTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array BRUSSELS = ['latitude' => '50.8503', 'longitude' => '4.3517'];
    private const array IXELLES = ['latitude' => '50.8333', 'longitude' => '4.3667'];
    private const array LIEGE = ['latitude' => '50.6326', 'longitude' => '5.5797'];

    public function test_the_announces_answering_the_member_ones_best_first(): void
    {
        $drum = InstrumentFactory::new()->asDrum()->create();
        $guitar = InstrumentFactory::new()->asGuitar()->create();
        $rock = StyleFactory::new()->asRock()->create();
        $pop = StyleFactory::new()->asPop()->create();
        $jazz = StyleFactory::new()->asJazz()->create();
        $member = UserFactory::new()->asBaseUser()->create();
        $own = $this->announce($member, MusicianAnnounce::TYPE_BAND, $drum, [$rock, $pop], 'Bruxelles', self::BRUSSELS, '-1 week');

        // Closer but no style in common, so after the one sharing two.
        $close = $this->announce($this->author('proche'), MusicianAnnounce::TYPE_MUSICIAN, $drum, [$jazz], 'Ixelles', self::IXELLES, '-2 days');
        $styled = $this->announce($this->author('rockpop'), MusicianAnnounce::TYPE_MUSICIAN, $drum, [$rock, $pop], 'Bruxelles', ['latitude' => '50.9', 'longitude' => '4.35'], '-3 days');
        // Too far, another instrument, the same type, too old, a deleted account.
        $this->announce($this->author('loin'), MusicianAnnounce::TYPE_MUSICIAN, $drum, [$rock], 'Liège', self::LIEGE, '-1 day');
        $this->announce($this->author('guitare'), MusicianAnnounce::TYPE_MUSICIAN, $guitar, [$rock], 'Bruxelles', self::BRUSSELS, '-1 day');
        $this->announce($this->author('batteur'), MusicianAnnounce::TYPE_BAND, $drum, [$rock], 'Bruxelles', self::BRUSSELS, '-1 day');
        $this->announce($this->author('ancien'), MusicianAnnounce::TYPE_MUSICIAN, $drum, [$rock], 'Bruxelles', self::BRUSSELS, '-3 months');
        $this->announce($this->author('parti', deleted: true), MusicianAnnounce::TYPE_MUSICIAN, $drum, [$rock], 'Bruxelles', self::BRUSSELS, '-1 day');

        $this->client->loginUser($member);
        $this->client->request('GET', '/api/user/musician/announces/matches');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AnnounceMatches',
            '@id' => '/api/user/musician/announces/matches',
            '@type' => 'AnnounceMatches',
            'matches' => [
                $this->match($styled, 'rockpop', 'Bruxelles', [['@type' => 'Style', 'name' => 'Rock'], ['@type' => 'Style', 'name' => 'Pop']], 5.5276628344037855, $own, ['Rock', 'Pop']),
                $this->match($close, 'proche', 'Ixelles', [['@type' => 'Style', 'name' => 'Jazz']], 2.1639238227150157, $own, []),
            ],
        ]);
    }

    public function test_only_the_member_three_latest_announces_are_matched(): void
    {
        $drum = InstrumentFactory::new()->asDrum()->create();
        $guitar = InstrumentFactory::new()->asGuitar()->create();
        $member = UserFactory::new()->asBaseUser()->create();
        // The oldest of four, so it is left out, and the guitar band answering it with it.
        $this->announce($member, MusicianAnnounce::TYPE_BAND, $guitar, [], 'Bruxelles', self::BRUSSELS, '-4 weeks');
        $own = $this->announce($member, MusicianAnnounce::TYPE_BAND, $drum, [], 'Bruxelles', self::BRUSSELS, '-1 week');
        $this->announce($member, MusicianAnnounce::TYPE_BAND, $drum, [], 'Bruxelles', self::BRUSSELS, '-2 weeks');
        $this->announce($member, MusicianAnnounce::TYPE_BAND, $drum, [], 'Bruxelles', self::BRUSSELS, '-3 weeks');
        $this->announce($this->author('guitare'), MusicianAnnounce::TYPE_MUSICIAN, $guitar, [], 'Bruxelles', self::BRUSSELS, '-1 day');
        $band = $this->announce($this->author('legroupe'), MusicianAnnounce::TYPE_MUSICIAN, $drum, [], 'Bruxelles', self::BRUSSELS, '-1 day');

        $this->client->loginUser($member);
        $this->client->request('GET', '/api/user/musician/announces/matches');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AnnounceMatches',
            '@id' => '/api/user/musician/announces/matches',
            '@type' => 'AnnounceMatches',
            // Answering the member's three drum announces, shown once, for the latest of them.
            'matches' => [$this->match($band, 'legroupe', 'Bruxelles', [], 0.0, $own, [])],
        ]);
    }

    public function test_no_matches_without_a_recent_announce(): void
    {
        $drum = InstrumentFactory::new()->asDrum()->create();
        $member = UserFactory::new()->asBaseUser()->create();
        $this->announce($member, MusicianAnnounce::TYPE_BAND, $drum, [], 'Bruxelles', self::BRUSSELS, '-3 months');
        $this->announce($this->author('proche'), MusicianAnnounce::TYPE_MUSICIAN, $drum, [], 'Bruxelles', self::BRUSSELS, '-1 day');

        $this->client->loginUser($member);
        $this->client->request('GET', '/api/user/musician/announces/matches');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AnnounceMatches',
            '@id' => '/api/user/musician/announces/matches',
            '@type' => 'AnnounceMatches',
            'matches' => [],
        ]);
    }

    public function test_a_guest_gets_no_matches(): void
    {
        $this->client->request('GET', '/api/user/musician/announces/matches');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    private function author(string $username, bool $deleted = false): User
    {
        return UserFactory::new()->create([
            'username' => $username,
            'email' => $username . '@test.com',
            'deletionDatetime' => $deleted ? new \DateTime('-1 day') : null,
        ]);
    }

    /**
     * @param array<\App\Entity\Attribute\Style> $styles
     * @param array{latitude: string, longitude: string} $point
     */
    private function announce(User $author, int $type, Instrument $instrument, array $styles, string $city, array $point, string $age): MusicianAnnounce
    {
        return MusicianAnnounceFactory::new()
            ->withInstrument($instrument)
            ->withStyles($styles)
            ->create([
                'type' => $type,
                'author' => $author,
                'locationName' => $city,
                'latitude' => $point['latitude'],
                'longitude' => $point['longitude'],
                'note' => 'Annonce de ' . $author->username,
                'creationDatetime' => new \DateTime($age),
            ]);
    }

    /**
     * @param list<array{'@type': string, name: string}> $styles
     * @param list<string> $sharedStyles
     *
     * @return array<string, mixed>
     */
    private function match(MusicianAnnounce $announce, string $username, string $city, array $styles, float $distance, MusicianAnnounce $own, array $sharedStyles): array
    {
        return [
            'announce' => [
                '@id' => '/api/announce_musicians/' . $announce->id,
                '@type' => 'AnnounceMusician',
                'id' => (string) $announce->id,
                'location_name' => $city,
                'note' => 'Annonce de ' . $username,
                'user' => ['@type' => 'User', 'id' => (string) $announce->author->id, 'username' => $username, 'has_musician_profile' => false],
                'instrument' => ['@type' => 'Instrument', 'name' => 'Batteur'],
                'type' => MusicianAnnounce::TYPE_MUSICIAN,
                'styles' => $styles,
                'distance' => $distance,
            ],
            '@type' => 'AnnounceMatchItem',
            'answered' => [
                '@type' => 'AnsweredAnnounce',
                'id' => (string) $own->id,
                'type' => MusicianAnnounce::TYPE_BAND,
                'instrument_name' => 'Batteur',
                'location_name' => 'Bruxelles',
            ],
            'shared_styles' => $sharedStyles,
        ];
    }
}
