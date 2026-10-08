<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace\AgendaEntry;

use App\Entity\BandSpace\AgendaEntry;
use App\Entity\BandSpace\AgendaEntryAvailability;
use App\Entity\BandSpace\AgendaEntryException;
use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\Notification\Notification;
use App\Entity\User;
use App\Enum\BandSpace\AgendaRecurrenceFrequency;
use App\Enum\BandSpace\AvailabilityAnswer;
use App\Enum\BandSpace\MembershipStatus;
use App\Enum\BandSpace\Role;
use App\Enum\Notification\NotificationType;
use App\Mercure\MercureTopic;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Double\RecordingHub;
use App\Tests\Factory\BandSpace\AgendaEntryAvailabilityFactory;
use App\Tests\Factory\BandSpace\AgendaEntryFactory;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\MemberAbsenceFactory;
use App\Tests\Factory\User\UserFactory;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * « Tu es disponible ? » for one date of an agenda entry (#1000).
 */
#[ResetDatabase]
class AgendaEntryAvailabilityTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array JSON = ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'];

    public function test_a_one_off_entry_lists_every_active_member_with_their_answer(): void
    {
        [$space, $author, $authorMembership, $yes, $absent, $pending] = $this->band();
        $entry = $this->entry($space, $author, self::day(10));
        $this->answer($entry, self::day(10), $yes, AvailabilityAnswer::Yes);
        MemberAbsenceFactory::new(['member' => $absent, 'startDate' => new DateTimeImmutable(self::day(9)), 'endDate' => new DateTimeImmutable(self::day(11))])->create();

        $this->client->loginUser($author);
        $this->client->request('GET', $this->url($space, $entry), [], [], self::JSON);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/AgendaEntryAvailability',
            '@id' => $this->url($space, $entry),
            '@type' => 'AgendaEntryAvailability',
            'band_space_id' => (string) $space->id,
            'entry_id' => (string) $entry->id,
            'occurrence_date' => self::day(10),
            'can_answer' => true,
            'can_remind' => true,
            'my_answer' => null,
            'totals' => ['yes' => 1, 'no' => 0, 'absent' => 1, 'pending' => 2],
            'members' => [
                $this->row($authorMembership, $author, 'Auteur', null, null),
                $this->row($yes, $yes->user, 'Oui', 'yes', '2026-01-01T10:00:00+00:00'),
                $this->row($absent, $absent->user, 'Absent', 'absent', null),
                $this->row($pending, $pending->user, 'Attente', null, null),
            ],
        ]);
    }

    public function test_a_recurring_entry_needs_the_occurrence(): void
    {
        [$space, $author] = $this->band();
        $entry = $this->weekly($space, $author, self::day(7));

        $this->client->loginUser($author);
        $this->client->request('GET', $this->url($space, $entry), [], [], self::JSON);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals($this->error(422, 'Précisez la date de l\'occurrence'));
    }

    public function test_a_date_the_entry_does_not_fall_on_is_not_found(): void
    {
        [$space, $author] = $this->band();
        $entry = $this->weekly($space, $author, self::day(7));

        $this->client->loginUser($author);
        $this->client->request('GET', $this->url($space, $entry) . '?occurrence=' . self::day(8), [], [], self::JSON);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals($this->error(404, 'Cet événement n\'a pas lieu à cette date'));
    }

    public function test_a_non_member_is_refused(): void
    {
        [$space, $author] = $this->band();
        $entry = $this->entry($space, $author, self::day(10));
        $outsider = UserFactory::new()->create(['username' => 'outsider', 'email' => 'outsider@example.com']);

        $this->client->loginUser($outsider);
        $this->client->request('GET', $this->url($space, $entry), [], [], self::JSON);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals($this->error(403, 'Vous n\'êtes pas membre de ce Band Space'));
    }

    public function test_a_member_answers_one_occurrence_of_a_series(): void
    {
        [$space, $author, , $yes] = $this->band();
        $entry = $this->weekly($space, $author, self::day(7));
        $hub = self::getContainer()->get(RecordingHub::class);

        $this->client->loginUser($yes->user);
        $this->client->jsonRequest('PUT', $this->url($space, $entry), ['occurrence_date' => self::day(14), 'answer' => 'no'], self::JSON);

        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertSame(self::day(14), $response['occurrence_date']);
        $this->assertSame('no', $response['my_answer']);
        $this->assertSame(['yes' => 0, 'no' => 1, 'absent' => 0, 'pending' => 3], $response['totals']);
        $this->assertFalse($response['can_remind'], 'A member who is neither the author nor an admin');

        $stored = $this->answersOf($entry);
        $this->assertSame([[self::day(14), 'no']], $stored);
        // Open screens refetch the agenda.
        $this->assertContains(
            json_encode(['type' => 'band_space_changed', 'band_space_id' => (string) $space->id, 'module' => 'agenda']),
            array_map(static fn ($update): string => $update->getData(), $hub->updates),
        );
    }

    public function test_answering_again_replaces_the_answer(): void
    {
        [$space, $author, , $yes] = $this->band();
        $entry = $this->entry($space, $author, self::day(10));
        $this->answer($entry, self::day(10), $yes, AvailabilityAnswer::No);

        $this->client->loginUser($yes->user);
        $this->client->jsonRequest('PUT', $this->url($space, $entry), ['answer' => 'yes'], self::JSON);

        $this->assertResponseIsSuccessful();
        $this->assertSame([[self::day(10), 'yes']], $this->answersOf($entry));
    }

    public function test_a_past_date_cannot_be_answered(): void
    {
        [$space, $author] = $this->band();
        $entry = $this->entry($space, $author, self::day(-2));

        $this->client->loginUser($author);
        $this->client->jsonRequest('PUT', $this->url($space, $entry), ['answer' => 'yes'], self::JSON);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals($this->error(422, 'Cette date est passée, les disponibilités ne peuvent plus changer'));
        $this->assertSame([], $this->answersOf($entry));
    }

    public function test_an_answer_other_than_yes_or_no_is_refused(): void
    {
        [$space, $author] = $this->band();
        $entry = $this->entry($space, $author, self::day(10));

        $this->client->loginUser($author);
        $this->client->jsonRequest('PUT', $this->url($space, $entry), ['answer' => 'maybe'], self::JSON);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/8e179f1b-97aa-4560-a02f-2a8b42e49df7',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'answer',
                    'message' => 'La réponse doit être « yes » ou « no »',
                    'code' => '8e179f1b-97aa-4560-a02f-2a8b42e49df7',
                ],
            ],
            'detail' => 'answer: La réponse doit être « yes » ou « no »',
            'description' => 'answer: La réponse doit être « yes » ou « no »',
            'type' => '/validation_errors/8e179f1b-97aa-4560-a02f-2a8b42e49df7',
            'title' => 'An error occurred',
        ]);
    }

    /**
     * Only the members still to answer: not the author, not who answered, not who declared an absence.
     */
    public function test_the_author_reminds_the_members_who_have_not_answered(): void
    {
        [$space, $author, , $yes, $absent, $pending] = $this->band();
        $entry = $this->entry($space, $author, self::day(10));
        $this->answer($entry, self::day(10), $yes, AvailabilityAnswer::Yes);
        MemberAbsenceFactory::new(['member' => $absent, 'startDate' => new DateTimeImmutable(self::day(10)), 'endDate' => new DateTimeImmutable(self::day(10))])->create();

        $this->client->loginUser($author);
        $this->client->jsonRequest('POST', $this->url($space, $entry) . '/remind', [], self::JSON);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $notifications = self::getContainer()->get(EntityManagerInterface::class)->getRepository(Notification::class)
            ->findBy(['type' => NotificationType::BandSpaceAgendaAvailabilityRequested]);
        $this->assertCount(1, $notifications);
        $this->assertSame((string) $pending->user->id, (string) $notifications[0]->recipient->id);
        $this->assertSame([
            'band_space_id' => (string) $space->id,
            'band_space_name' => 'Les Rockeurs',
            'agenda_entry_id' => (string) $entry->id,
            'entry_title' => 'Concert au Bota',
            'occurrence_date' => self::day(10),
            'event_datetime' => self::day(10) . 'T20:00:00+00:00',
            'is_all_day' => false,
            'actor_id' => (string) $author->id,
            'actor_username' => 'auteur',
        ], $notifications[0]->payload);
    }

    public function test_a_member_who_is_neither_author_nor_admin_cannot_remind(): void
    {
        [$space, $author, , $yes] = $this->band();
        $entry = $this->entry($space, $author, self::day(10));

        $this->client->loginUser($yes->user);
        $this->client->jsonRequest('POST', $this->url($space, $entry) . '/remind', [], self::JSON);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals($this->error(403, 'Seul l\'auteur de l\'événement ou un administrateur peut relancer les membres'));
    }

    public function test_reminding_when_everybody_answered_sends_nothing(): void
    {
        [$space, $author, $authorMembership, $yes, $absent, $pending] = $this->band();
        $entry = $this->entry($space, $author, self::day(10));
        foreach ([$authorMembership, $yes, $absent, $pending] as $membership) {
            $this->answer($entry, self::day(10), $membership, AvailabilityAnswer::Yes);
        }

        $this->client->loginUser($author);
        $this->client->jsonRequest('POST', $this->url($space, $entry) . '/remind', [], self::JSON);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame([], self::getContainer()->get(EntityManagerInterface::class)->getRepository(Notification::class)
            ->findBy(['type' => NotificationType::BandSpaceAgendaAvailabilityRequested]));
    }

    public function test_a_second_reminder_within_twelve_hours_is_refused(): void
    {
        [$space, $author] = $this->band();
        $entry = $this->entry($space, $author, self::day(10));
        // The first reminder, seeded on the limiter itself: loginUser() authenticates one request per test.
        self::getContainer()->get('limiter.agenda_availability_reminder')->create($entry->id . '|' . self::day(10))->consume();

        $this->client->loginUser($author);
        $this->client->jsonRequest('POST', $this->url($space, $entry) . '/remind', [], self::JSON);

        $this->assertResponseStatusCodeSame(Response::HTTP_TOO_MANY_REQUESTS);
        $this->assertJsonEquals($this->error(429, 'Les membres ont déjà été relancés pour cette date, réessayez plus tard'));
    }

    /** The home card's « 3 dispos · 1 sans réponse » comes with the feed, one count per occurrence. */
    public function test_the_agenda_feed_carries_each_occurrence_totals(): void
    {
        [$space, $author, , $yes, $absent] = $this->band();
        $entry = $this->weekly($space, $author, self::day(7));
        $this->answer($entry, self::day(14), $yes, AvailabilityAnswer::No);
        MemberAbsenceFactory::new(['member' => $absent, 'startDate' => new DateTimeImmutable(self::day(7)), 'endDate' => new DateTimeImmutable(self::day(7))])->create();

        $this->client->loginUser($author);
        $this->client->request('GET', '/api/band_spaces/' . $space->id . '/agenda?from=' . self::day(7) . '&to=' . self::day(14), [], [], self::JSON);

        $this->assertResponseIsSuccessful();
        $occurrences = [];
        foreach ($this->getResponseAsArray()['member'] as $item) {
            if ($item['source'] === 'manual') {
                $occurrences[$item['metadata']['occurrence_date']] = $item['metadata']['availability'];
            }
        }
        $this->assertSame([
            self::day(7) => ['yes' => 0, 'no' => 0, 'absent' => 1, 'pending' => 3],
            self::day(14) => ['yes' => 0, 'no' => 1, 'absent' => 0, 'pending' => 3],
        ], $occurrences);
    }

    /** Who left or was kicked keeps their row, but no longer counts anywhere. */
    public function test_a_former_member_answer_does_not_count_in_the_feed(): void
    {
        [$space, $author, , , , $pending] = $this->band();
        $entry = $this->entry($space, $author, self::day(10));
        $this->answer($entry, self::day(10), $pending, AvailabilityAnswer::Yes);
        $pending->status = MembershipStatus::Kicked;
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        $this->client->loginUser($author);
        $this->client->request('GET', '/api/band_spaces/' . $space->id . '/agenda?from=' . self::day(10) . '&to=' . self::day(10), [], [], self::JSON);

        $this->assertResponseIsSuccessful();
        $manual = array_values(array_filter($this->getResponseAsArray()['member'], static fn (array $item): bool => $item['source'] === 'manual'));
        $this->assertSame(['yes' => 0, 'no' => 0, 'absent' => 0, 'pending' => 3], $manual[0]['metadata']['availability']);
    }

    public function test_an_admin_who_is_not_the_author_can_remind(): void
    {
        [$space, $author, , $yes] = $this->band();
        $yes->role = Role::Admin;
        self::getContainer()->get(EntityManagerInterface::class)->flush();
        $entry = $this->entry($space, $author, self::day(10));

        $this->client->loginUser($yes->user);
        $this->client->jsonRequest('POST', $this->url($space, $entry) . '/remind', [], self::JSON);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertCount(3, self::getContainer()->get(EntityManagerInterface::class)->getRepository(Notification::class)
            ->findBy(['type' => NotificationType::BandSpaceAgendaAvailabilityRequested]));
    }

    public function test_a_past_date_cannot_be_reminded(): void
    {
        [$space, $author] = $this->band();
        $entry = $this->entry($space, $author, self::day(-2));

        $this->client->loginUser($author);
        $this->client->jsonRequest('POST', $this->url($space, $entry) . '/remind', [], self::JSON);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals($this->error(422, 'Cette date est passée, les disponibilités ne peuvent plus changer'));
    }

    public function test_a_past_date_is_read_only_for_everybody(): void
    {
        [$space, $author] = $this->band();
        $entry = $this->entry($space, $author, self::day(-2));

        $this->client->loginUser($author);
        $this->client->request('GET', $this->url($space, $entry), [], [], self::JSON);

        $this->assertResponseIsSuccessful();
        $response = $this->getResponseAsArray();
        $this->assertFalse($response['can_answer']);
        $this->assertFalse($response['can_remind']);
    }

    public function test_answering_a_series_needs_the_occurrence(): void
    {
        [$space, $author] = $this->band();
        $entry = $this->weekly($space, $author, self::day(7));

        $this->client->loginUser($author);
        $this->client->jsonRequest('PUT', $this->url($space, $entry), ['answer' => 'yes'], self::JSON);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals($this->error(422, 'Précisez la date de l\'occurrence'));
    }

    public function test_a_cancelled_occurrence_cannot_be_answered(): void
    {
        [$space, $author] = $this->band();
        $entry = $this->weekly($space, $author, self::day(7));
        $cancelled = new AgendaEntryException();
        $cancelled->agendaEntry = $entry;
        $cancelled->occurrenceDate = new DateTimeImmutable(self::day(14));
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($cancelled);
        $entityManager->flush();
        // The request shares this EntityManager, and the entry it would find there still holds the
        // empty `exceptions` collection the factory built; reloading it is what a real request does.
        $entityManager->clear();

        $this->client->loginUser($author);
        $this->client->jsonRequest('PUT', $this->url($space, $entry), ['occurrence_date' => self::day(14), 'answer' => 'yes'], self::JSON);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals($this->error(404, 'Cet événement n\'a pas lieu à cette date'));
    }

    public function test_an_entry_of_another_band_is_not_found(): void
    {
        [$space, $author] = $this->band();
        $otherEntry = $this->entry(BandSpaceFactory::new()->create(), UserFactory::new()->create(['username' => 'ailleurs', 'email' => 'ailleurs@example.com']), self::day(10));

        $this->client->loginUser($author);
        $this->client->jsonRequest('PUT', '/api/band_spaces/' . $space->id . '/agenda-entries/' . $otherEntry->id . '/availability', ['answer' => 'yes'], self::JSON);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals($this->error(404, 'Événement introuvable'));
    }

    public function test_a_blank_answer_is_one_violation(): void
    {
        [$space, $author] = $this->band();
        $entry = $this->entry($space, $author, self::day(10));

        $this->client->loginUser($author);
        $this->client->jsonRequest('PUT', $this->url($space, $entry), ['answer' => ''], self::JSON);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'answer',
                    'message' => 'Veuillez indiquer votre disponibilité',
                    'code' => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                ],
            ],
            'detail' => 'answer: Veuillez indiquer votre disponibilité',
            'description' => 'answer: Veuillez indiquer votre disponibilité',
            'type' => '/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            'title' => 'An error occurred',
        ]);
    }

    /**
     * 00:30 in Paris on the 11th is 22:30 UTC on the 10th: the key is the UTC date, the one a
     * cancellation uses and the one every agenda item carries as `occurrence_date`. A client must
     * send that, never a date it derived from local time.
     */
    public function test_an_occurrence_is_keyed_by_its_utc_date(): void
    {
        [$space, $author] = $this->band();
        $entry = AgendaEntryFactory::new([
            'bandSpace' => $space,
            'creator' => $author,
            'title' => 'After',
            'eventDatetime' => new DateTimeImmutable(self::day(10) . ' 22:30:00', new DateTimeZone('UTC')),
        ])->create();

        $this->client->loginUser($author);
        $this->client->request('GET', $this->url($space, $entry) . '?occurrence=' . self::day(11), [], [], self::JSON);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals($this->error(404, 'Cet événement n\'a pas lieu à cette date'));
    }

    /**
     * An author, three members, one who left, all named by their stage name so the expected bodies
     * do not depend on profile defaults.
     *
     * @return array{0: BandSpace, 1: User, 2: BandSpaceMembership, 3: BandSpaceMembership, 4: BandSpaceMembership, 5: BandSpaceMembership}
     */
    private function band(): array
    {
        $space = BandSpaceFactory::new()->create(['name' => 'Les Rockeurs']);
        $author = UserFactory::new()->create(['username' => 'auteur', 'email' => 'auteur@example.com']);
        $authorMembership = BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $author, 'role' => Role::User, 'stageName' => 'Auteur', 'creationDatetime' => new \DateTime('2026-01-01 10:00:00')])->create();
        $members = [];
        foreach (['oui' => 'Oui', 'absent' => 'Absent', 'attente' => 'Attente'] as $username => $stageName) {
            $user = UserFactory::new()->create(['username' => $username, 'email' => $username . '@example.com']);
            $members[] = BandSpaceMembershipFactory::new([
                'bandSpace' => $space,
                'user' => $user,
                'stageName' => $stageName,
                'creationDatetime' => new \DateTime('2026-01-0' . (count($members) + 2) . ' 10:00:00'),
            ])->create();
        }
        $left = UserFactory::new()->create(['username' => 'parti', 'email' => 'parti@example.com']);
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $left, 'status' => MembershipStatus::Left])->create();

        return [$space, $author, $authorMembership, ...$members];
    }

    private function entry(BandSpace $space, User $author, string $day): AgendaEntry
    {
        return AgendaEntryFactory::new([
            'bandSpace' => $space,
            'creator' => $author,
            'title' => 'Concert au Bota',
            'eventDatetime' => new DateTimeImmutable($day . ' 20:00:00', new DateTimeZone('UTC')),
        ])->create();
    }

    private function weekly(BandSpace $space, User $author, string $day): AgendaEntry
    {
        return AgendaEntryFactory::new([
            'bandSpace' => $space,
            'creator' => $author,
            'title' => 'Répétition',
            'eventDatetime' => new DateTimeImmutable($day . ' 18:00:00', new DateTimeZone('UTC')),
            'recurrenceFrequency' => AgendaRecurrenceFrequency::Weekly,
            'recurrenceUntilDate' => new DateTimeImmutable(self::day(60)),
        ])->create();
    }

    private function answer(AgendaEntry $entry, string $day, BandSpaceMembership $membership, AvailabilityAnswer $answer): void
    {
        AgendaEntryAvailabilityFactory::new([
            'agendaEntry' => $entry,
            'occurrenceDate' => new DateTimeImmutable($day),
            'membership' => $membership,
            'answer' => $answer,
        ])->create();
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function answersOf(AgendaEntry $entry): array
    {
        $rows = self::getContainer()->get(EntityManagerInterface::class)->getRepository(AgendaEntryAvailability::class)
            ->findBy(['agendaEntry' => (string) $entry->id]);

        return array_map(static fn (AgendaEntryAvailability $row): array => [$row->occurrenceDate->format('Y-m-d'), $row->answer->value], $rows);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(BandSpaceMembership $membership, User $user, string $name, ?string $answer, ?string $answeredAt): array
    {
        return [
            'membership_id' => (string) $membership->id,
            'user_id' => (string) $user->id,
            'display_name' => $name,
            'profile_picture_url' => null,
            'answer' => $answer,
            'answered_at' => $answeredAt,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function error(int $status, string $detail): array
    {
        return [
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/' . $status,
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => $detail,
            'status' => $status,
            'type' => '/errors/' . $status,
            'description' => $detail,
        ];
    }

    private function url(BandSpace $space, AgendaEntry $entry): string
    {
        return '/api/band_spaces/' . $space->id . '/agenda-entries/' . $entry->id . '/availability';
    }

    private static function day(int $offset): string
    {
        return (new DateTimeImmutable($offset . ' days', new DateTimeZone('UTC')))->format('Y-m-d');
    }
}
