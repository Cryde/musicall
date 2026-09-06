<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Agenda;

use App\Entity\BandSpace\AgendaFeedToken;
use App\Enum\BandSpace\MembershipStatus;
use App\Enum\BandSpace\TaskPriority;
use App\Enum\BandSpace\TaskStatus;
use App\Repository\BandSpace\AgendaFeedTokenRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\AgendaEntryFactory;
use App\Tests\Factory\BandSpace\AgendaFeedTokenFactory;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\FinanceCategoryFactory;
use App\Tests\Factory\BandSpace\FinanceEntryFactory;
use App\Tests\Factory\BandSpace\MemberAbsenceFactory;
use App\Tests\Factory\BandSpace\TaskFactory;
use App\Tests\Factory\User\UserFactory;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class AgendaFeedTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const string KNOWN_TOKEN = 'a-token-only-this-test-knows';

    public function test_a_member_generates_a_feed_and_only_the_hash_is_stored(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-feed',
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $response = $this->getResponseAsArray();

        // The token is random, so the URL cannot be asserted whole. What matters is its shape and
        // that the row behind it holds the hash of the token the caller was just handed.
        $this->assertMatchesRegularExpression(
            '#^https?://[^/]+/api/shares/agenda/[A-Za-z0-9_\-]+\.ics$#',
            $response['feed_url'],
        );

        $tokens = $this->feedTokenRepository()->findBy(['membership' => $membership]);
        $this->assertCount(1, $tokens);
        $this->assertSame(hash('sha256', $this->tokenFromUrl($response['feed_url'])), $tokens[0]->tokenHash);
        $this->assertSame(0, $tokens[0]->accessCount);
        $this->assertNull($tokens[0]->lastAccessDatetime);
        $this->assertSame(
            $tokens[0]->creationDatetime->format(\DateTimeInterface::ATOM),
            $response['creation_datetime'],
        );
    }

    public function test_generating_twice_rotates_the_token_in_place(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        AgendaFeedTokenFactory::new(['membership' => $membership])->withPlainToken(self::KNOWN_TOKEN)->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/agenda-feed',
            [],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        // A member holds one feed per band, never two: the old URL is dead the moment a new one is
        // handed out, which is what makes this the revoke and replace action the UI offers.
        $tokens = $this->feedTokenRepository()->findBy(['membership' => $membership]);
        $this->assertCount(1, $tokens);
        $this->assertNotSame(hash('sha256', self::KNOWN_TOKEN), $tokens[0]->tokenHash);
        // The counters described the URL that has just been retired, so they go with it. Kept, a
        // link minted a second ago would report itself as already fetched nine times.
        $this->assertSame(0, $tokens[0]->accessCount);
        $this->assertNull($tokens[0]->lastAccessDatetime);
        $this->assertGreaterThan(
            new DateTimeImmutable('-1 hour'),
            $tokens[0]->creationDatetime,
        );
    }

    public function test_a_non_member_cannot_generate_a_feed(): void
    {
        $stranger = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();

        $this->client->loginUser($stranger);
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $bandSpace->id . '/agenda-feed', [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'status' => 403,
            'type' => '/errors/403',
            'title' => 'An error occurred',
            'detail' => 'Vous n\'êtes pas membre de ce Band Space',
            'description' => 'Vous n\'êtes pas membre de ce Band Space',
        ]);
        $this->assertCount(0, $this->feedTokenRepository()->findAll());
    }

    public function test_a_non_member_cannot_revoke_a_feed(): void
    {
        $member = UserFactory::new()->asBaseUser()->create();
        $stranger = UserFactory::new()->asBaseUser()->create([
            'username' => 'a_stranger',
            'email' => 'a_stranger@email.com',
        ]);
        $bandSpace = BandSpaceFactory::new()->create();
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $member])->create();
        AgendaFeedTokenFactory::new(['membership' => $membership])->withPlainToken(self::KNOWN_TOKEN)->create();

        $this->client->loginUser($stranger);
        $this->client->jsonRequest('DELETE', '/api/band_spaces/' . $bandSpace->id . '/agenda-feed', [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'status' => 403,
            'type' => '/errors/403',
            'title' => 'An error occurred',
            'detail' => 'Vous n\'êtes pas membre de ce Band Space',
            'description' => 'Vous n\'êtes pas membre de ce Band Space',
        ]);
        // The member's feed is untouched: a stranger cannot cut off someone else's subscription.
        $this->assertCount(1, $this->feedTokenRepository()->findBy(['membership' => $membership]));
    }

    public function test_revoking_when_there_is_no_feed_is_a_404(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('DELETE', '/api/band_spaces/' . $bandSpace->id . '/agenda-feed', [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'status' => 404,
            'type' => '/errors/404',
            'title' => 'An error occurred',
            'detail' => 'Aucun flux à révoquer',
            'description' => 'Aucun flux à révoquer',
        ]);
    }

    public function test_the_state_endpoint_reports_an_existing_feed(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        AgendaFeedTokenFactory::new([
            'membership' => $membership,
            'creationDatetime' => new DateTimeImmutable('2026-09-01 10:00:00', new DateTimeZone('UTC')),
            'lastAccessDatetime' => new DateTimeImmutable('2026-09-05 08:30:00', new DateTimeZone('UTC')),
            'accessCount' => 7,
        ])->withPlainToken(self::KNOWN_TOKEN)->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/band_spaces/' . $bandSpace->id . '/agenda-feed', [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AgendaFeed',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/agenda-feed',
            '@type' => 'AgendaFeed',
            'band_space_id' => (string) $bandSpace->id,
            'is_enabled' => true,
            // Never the URL: only the hash was stored, so it cannot be shown again.
            'creation_datetime' => '2026-09-01T10:00:00+00:00',
            'last_access_datetime' => '2026-09-05T08:30:00+00:00',
            'access_count' => 7,
        ]);
    }

    public function test_the_state_endpoint_reports_no_feed(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('GET', '/api/band_spaces/' . $bandSpace->id . '/agenda-feed', [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AgendaFeed',
            '@id' => '/api/band_spaces/' . $bandSpace->id . '/agenda-feed',
            '@type' => 'AgendaFeed',
            'band_space_id' => (string) $bandSpace->id,
            'is_enabled' => false,
            'creation_datetime' => null,
            'last_access_datetime' => null,
            'access_count' => 0,
        ]);
    }

    public function test_a_non_member_cannot_read_the_feed_state(): void
    {
        $stranger = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();

        $this->client->loginUser($stranger);
        $this->client->jsonRequest('GET', '/api/band_spaces/' . $bandSpace->id . '/agenda-feed', [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_revoking_deletes_the_token(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        AgendaFeedTokenFactory::new(['membership' => $membership])->withPlainToken(self::KNOWN_TOKEN)->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('DELETE', '/api/band_spaces/' . $bandSpace->id . '/agenda-feed', [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertCount(0, $this->feedTokenRepository()->findBy(['membership' => $membership]));
    }

    public function test_leaving_the_band_kills_the_feed(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $other = UserFactory::new()->asBaseUser()->create([
            'username' => 'second_member',
            'email' => 'second_member@email.com',
        ]);
        $bandSpace = BandSpaceFactory::new()->create();
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        // A second admin, so leaving is not refused for emptying the band of admins.
        BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => $other,
            'role' => \App\Enum\BandSpace\Role::Admin,
        ])->create();
        AgendaFeedTokenFactory::new(['membership' => $membership])->withPlainToken(self::KNOWN_TOKEN)->create();

        $this->client->loginUser($user);
        $this->client->jsonRequest('POST', '/api/band_spaces/' . $bandSpace->id . '/leave', [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertCount(0, $this->feedTokenRepository()->findBy(['membership' => $membership]));
    }

    public function test_the_feed_publishes_manual_entries_and_nothing_else(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new(['name' => 'Les Copains'])->create();
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        AgendaFeedTokenFactory::new(['membership' => $membership])->withPlainToken(self::KNOWN_TOKEN)->create();

        AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'title' => 'Répétition générale',
            'description' => 'Préparer le set',
            'location' => 'Studio B',
            'eventDatetime' => new DateTimeImmutable('+10 days 20:00:00', new DateTimeZone('UTC')),
            'endDatetime' => new DateTimeImmutable('+10 days 23:00:00', new DateTimeZone('UTC')),
        ])->create();

        TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'title' => 'Acheter des cordes',
            'status' => TaskStatus::Todo,
            'priority' => TaskPriority::High,
            'dueDate' => new DateTimeImmutable('+12 days 12:00:00', new DateTimeZone('UTC')),
        ])->create();

        $financeCategory = FinanceCategoryFactory::new(['bandSpace' => $bandSpace, 'name' => 'Logistique'])->create();
        FinanceEntryFactory::new([
            'category' => $financeCategory,
            'label' => 'Location salle',
            'date' => new \DateTime('+13 days', new DateTimeZone('UTC')),
        ])->create();

        MemberAbsenceFactory::new([
            'member' => $membership,
            'startDate' => new DateTimeImmutable('+14 days', new DateTimeZone('UTC')),
            'endDate' => new DateTimeImmutable('+15 days', new DateTimeZone('UTC')),
        ])->create();

        $this->client->request('GET', '/api/shares/agenda/' . self::KNOWN_TOKEN . '.ics');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/calendar; charset=utf-8');
        $this->assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');

        $feed = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString('BEGIN:VCALENDAR', $feed);
        $this->assertStringContainsString("X-WR-CALNAME:Les Copains\r\n", $feed);
        $this->assertStringContainsString("SUMMARY:Répétition générale\r\n", $feed);
        $this->assertStringContainsString("LOCATION:Studio B\r\n", $feed);
        $this->assertSame(1, substr_count($feed, 'BEGIN:VEVENT'));

        // The three sources a personal calendar has no use for. A subscribed calendar has no filter
        // chips and one colour, so they would be noise the subscriber deletes the feed over.
        $this->assertStringNotContainsString('Acheter des cordes', $feed);
        $this->assertStringNotContainsString('Location salle', $feed);
        $this->assertStringNotContainsString('indisponible', $feed);
    }

    public function test_fetching_the_feed_records_the_access(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        AgendaFeedTokenFactory::new(['membership' => $membership, 'accessCount' => 4])
            ->withPlainToken(self::KNOWN_TOKEN)
            ->create();

        $this->client->request('GET', '/api/shares/agenda/' . self::KNOWN_TOKEN . '.ics');

        $this->assertResponseIsSuccessful();

        $tokens = $this->feedTokenRepository()->findBy(['membership' => $membership]);
        $this->assertSame(5, $tokens[0]->accessCount);
        $this->assertInstanceOf(DateTimeImmutable::class, $tokens[0]->lastAccessDatetime);
    }

    public function test_a_matching_etag_is_answered_with_a_304(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        AgendaFeedTokenFactory::new(['membership' => $membership])->withPlainToken(self::KNOWN_TOKEN)->create();
        AgendaEntryFactory::new([
            'bandSpace' => $bandSpace,
            'creator' => $user,
            'title' => 'Concert',
            'eventDatetime' => new DateTimeImmutable('+10 days 20:00:00', new DateTimeZone('UTC')),
        ])->create();

        $this->client->request('GET', '/api/shares/agenda/' . self::KNOWN_TOKEN . '.ics');
        $this->assertResponseIsSuccessful();
        $etag = $this->client->getResponse()->getEtag();
        $this->assertNotNull($etag);

        $this->client->request('GET', '/api/shares/agenda/' . self::KNOWN_TOKEN . '.ics', [], [], [
            'HTTP_IF_NONE_MATCH' => $etag,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_MODIFIED);
        $this->assertEmpty($this->client->getResponse()->getContent());
    }

    public function test_an_unknown_token_is_a_404(): void
    {
        $this->client->request('GET', '/api/shares/agenda/nobody-minted-this.ics');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonContains(['detail' => 'Flux introuvable']);
    }

    public function test_a_kicked_member_keeps_no_feed(): void
    {
        // The FK cascade never fires here: a membership row survives being kicked, it only changes
        // status. Without the provider's own check the URL would keep serving the band's dates.
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $membership = BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => $user,
            'status' => MembershipStatus::Kicked,
        ])->create();
        AgendaFeedTokenFactory::new(['membership' => $membership])->withPlainToken(self::KNOWN_TOKEN)->create();

        $this->client->request('GET', '/api/shares/agenda/' . self::KNOWN_TOKEN . '.ics');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonContains(['detail' => 'Flux introuvable']);
    }

    public function test_the_feed_needs_no_authentication(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $membership = BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        AgendaFeedTokenFactory::new(['membership' => $membership])->withPlainToken(self::KNOWN_TOKEN)->create();

        // No loginUser: a calendar client sends neither a cookie nor a JWT, so a 401 here would mean
        // the endpoint is unusable by the only callers it has.
        $this->client->request('GET', '/api/shares/agenda/' . self::KNOWN_TOKEN . '.ics');

        $this->assertResponseIsSuccessful();
    }

    private function feedTokenRepository(): AgendaFeedTokenRepository
    {
        /** @var AgendaFeedTokenRepository $repository */
        $repository = self::getContainer()->get(AgendaFeedTokenRepository::class);

        return $repository;
    }

    private function tokenFromUrl(string $feedUrl): string
    {
        return basename(parse_url($feedUrl, PHP_URL_PATH) ?: '', '.ics');
    }
}
