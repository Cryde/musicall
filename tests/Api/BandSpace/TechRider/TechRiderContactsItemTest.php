<?php declare(strict_types=1);

namespace App\Tests\Api\BandSpace\TechRider;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\BandSpace\TechRider;
use App\Entity\BandSpace\TechRiderItem;
use App\Entity\User;
use App\Enum\BandSpace\MembershipStatus;
use App\Enum\BandSpace\TechRiderItemType;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Attribute\InstrumentFactory;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\TechRiderFactory;
use App\Tests\Factory\BandSpace\TechRiderItemFactory;
use App\Tests\Factory\User\UserFactory;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * A contacts item holds no copy of the member list. It renders from the roster on every read, so a
 * rider cannot quietly go stale the moment somebody joins or leaves, which is the failure the
 * venue would be the one to discover.
 */
#[ResetDatabase]
class TechRiderContactsItemTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array HEADERS = [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
    ];

    public function test_the_item_renders_one_line_per_active_member(): void
    {
        [$user, $bandSpace, $rider, $item] = $this->seed();
        $this->seedBand($bandSpace, $user);

        $this->client->loginUser($user);
        $this->client->request('GET', $this->riderUrl($bandSpace, $rider), [], [], self::HEADERS);

        $this->assertResponseIsSuccessful();

        $contacts = $this->contactsOf($item);
        $this->assertSame([
            'show_emails' => false,
            'lines' => [
                'Geoffrey /// Chant',
                // Two instruments join with a comma, which is how a member holds more than one
                // line on a rider.
                'Kenny /// Basse, Chœurs',
                // No instruments: the name alone, with no dangling separator behind it.
                'Roadie',
                'jeremy_login /// Batterie',
            ],
            'emails' => [],
        ], $contacts);
    }

    /**
     * The full item body once, so a change anywhere else in the shape shows up here rather than
     * only in the tests that happen to assert that field.
     */
    public function test_the_full_item_body_carries_the_rendered_block(): void
    {
        [$user, $bandSpace, $rider, $item] = $this->seed();
        $this->seedBand($bandSpace, $user);

        $this->client->loginUser($user);
        $this->client->request('GET', $this->itemUrl($bandSpace, $rider, $item), [], [], self::HEADERS);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TechRiderItem',
            '@id' => $this->itemUrl($bandSpace, $rider, $item),
            '@type' => 'TechRiderItem',
            'id' => (string) $item->id,
            'band_space_id' => (string) $bandSpace->id,
            'rider_id' => (string) $rider->id,
            'type' => 'contacts',
            'is_included' => true,
            'title' => 'Membres et contacts',
            'content' => null,
            'file' => null,
            'patch_list' => null,
            'contacts' => [
                'show_emails' => false,
                'lines' => [
                    'Geoffrey /// Chant',
                    'Kenny /// Basse, Chœurs',
                    'Roadie',
                    'jeremy_login /// Batterie',
                ],
                'emails' => [],
            ],
            'position' => 0,
            'creation_datetime' => $item->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => $item->updateDatetime?->format(\DateTimeInterface::ATOM),
        ]);
    }

    /** The whole point of rendering live rather than storing a copy. */
    public function test_a_member_who_left_is_absent(): void
    {
        [$user, $bandSpace, $rider, $item] = $this->seed();
        $this->seedBand($bandSpace, $user);

        $gone = UserFactory::new()->create(['username' => 'ancien', 'email' => 'ancien@test.com']);
        BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => $gone,
            'stageName' => 'Ancien',
            'status' => MembershipStatus::Left,
            'leftDatetime' => new DateTime('-1 day'),
        ])->create();

        $this->client->loginUser($user);
        $this->client->request('GET', $this->riderUrl($bandSpace, $rider), [], [], self::HEADERS);
        $this->assertResponseIsSuccessful();

        $this->assertNotContains('Ancien', $this->contactsOf($item)['lines']);
    }

    /** Off by default, because this document is sent to strangers. */
    public function test_emails_are_hidden_unless_asked_for(): void
    {
        [$user, $bandSpace, $rider, $item] = $this->seed();
        $this->seedBand($bandSpace, $user);

        $this->client->loginUser($user);
        $this->client->request('GET', $this->riderUrl($bandSpace, $rider), [], [], self::HEADERS);
        $this->assertResponseIsSuccessful();

        $contacts = $this->contactsOf($item);
        $this->assertFalse($contacts['show_emails']);
        $this->assertSame([], $contacts['emails']);
    }

    public function test_emails_appear_once_the_choice_is_recorded(): void
    {
        [$user, $bandSpace, $rider, $item] = $this->seed();
        $this->seedBand($bandSpace, $user);

        $item->content = ['showEmails' => true];
        self::getContainer()->get('doctrine')->getManager()->flush();

        $this->client->loginUser($user);
        $this->client->request('GET', $this->riderUrl($bandSpace, $rider), [], [], self::HEADERS);
        $this->assertResponseIsSuccessful();

        $contacts = $this->contactsOf($item);
        $this->assertTrue($contacts['show_emails']);
        $this->assertSame([
            'geoffrey@test.com',
            'kenny@test.com',
            'roadie@test.com',
            'jeremy@test.com',
        ], $contacts['emails']);
    }

    /** A missing or malformed choice is read as off, never as on. */
    public function test_a_content_without_the_flag_hides_emails(): void
    {
        [$user, $bandSpace, $rider, $item] = $this->seed();
        $this->seedBand($bandSpace, $user);

        $item->content = ['note' => ['type' => 'doc', 'content' => []]];
        self::getContainer()->get('doctrine')->getManager()->flush();

        $this->client->loginUser($user);
        $this->client->request('GET', $this->riderUrl($bandSpace, $rider), [], [], self::HEADERS);
        $this->assertResponseIsSuccessful();

        $this->assertFalse($this->contactsOf($item)['show_emails']);
        $this->assertSame([], $this->contactsOf($item)['emails']);
    }

    /** Only a contacts item carries the block: every other type reports null. */
    public function test_other_item_types_carry_no_contacts_block(): void
    {
        [$user, $bandSpace, $rider] = $this->seed();
        $textItem = TechRiderItemFactory::new([
            'techRider' => $rider,
            'type' => TechRiderItemType::Text,
            'title' => 'Sonorisation',
            'position' => 1,
        ])->create();

        $this->client->loginUser($user);
        $this->client->request('GET', $this->riderUrl($bandSpace, $rider), [], [], self::HEADERS);
        $this->assertResponseIsSuccessful();

        $this->assertNull($this->itemPayload((string) $textItem->id)['contacts']);
    }

    public function test_a_non_member_cannot_read_the_rider(): void
    {
        [, $bandSpace, $rider] = $this->seed();
        $outsider = UserFactory::new()->create(['username' => 'outsider', 'email' => 'outsider@test.com']);

        $this->client->loginUser($outsider);
        $this->client->request('GET', $this->riderUrl($bandSpace, $rider), [], [], self::HEADERS);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => "Vous n'êtes pas membre de ce Band Space",
            'status' => 403,
            'type' => '/errors/403',
            'description' => "Vous n'êtes pas membre de ce Band Space",
        ]);
    }

    /**
     * @return array{User, BandSpace, TechRider, TechRiderItem}
     */
    private function seed(): array
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => 'jeremy_login',
            'email' => 'jeremy@test.com',
        ]);
        $bandSpace = BandSpaceFactory::new()->create();
        // Deliberately no stage name: this member exercises the username fallback.
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();
        $rider = TechRiderFactory::new(['bandSpace' => $bandSpace, 'name' => 'Rider'])->create();
        $item = TechRiderItemFactory::new([
            'techRider' => $rider,
            'type' => TechRiderItemType::Contacts,
            'title' => 'Membres et contacts',
            'position' => 0,
        ])->create();

        return [$user, $bandSpace, $rider, $item];
    }

    /**
     * The reference rider's line-up: someone with two instruments, someone with none, and one
     * member who never set a stage name.
     */
    private function seedBand(BandSpace $bandSpace, User $actingUser): void
    {
        $entityManager = self::getContainer()->get('doctrine')->getManager();
        $drums = InstrumentFactory::new()->asDrum()->create();
        $bass = InstrumentFactory::new()->asBass()->create();
        $vocals = InstrumentFactory::new()->asBackingVocals()->create();
        $lead = InstrumentFactory::new()->create([
            'name' => 'Chant',
            'musicianName' => 'Chanteur',
            'slug' => 'chant',
        ]);

        // The acting membership already exists, so its instruments are attached rather than
        // seeded through the factory.
        $jeremy = $this->membershipRepository()->findMembership($bandSpace, $actingUser);
        self::assertInstanceOf(BandSpaceMembership::class, $jeremy);
        $jeremy->instruments->add($drums);

        BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => UserFactory::new()->create(['username' => 'kenny_login', 'email' => 'kenny@test.com']),
            'stageName' => 'Kenny',
            'instruments' => new ArrayCollection([$bass, $vocals]),
        ])->create();

        BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => UserFactory::new()->create(['username' => 'geoffrey_login', 'email' => 'geoffrey@test.com']),
            'stageName' => 'Geoffrey',
            'instruments' => new ArrayCollection([$lead]),
        ])->create();

        // No instruments: this member proves a bare name prints without a dangling separator.
        BandSpaceMembershipFactory::new([
            'bandSpace' => $bandSpace,
            'user' => UserFactory::new()->create(['username' => 'roadie_login', 'email' => 'roadie@test.com']),
            'stageName' => 'Roadie',
        ])->create();

        $entityManager->flush();
    }

    /**
     * @return array<string, mixed>
     */
    private function contactsOf(TechRiderItem $item): array
    {
        return $this->itemPayload((string) $item->id)['contacts'];
    }

    /**
     * @return array<string, mixed>
     */
    private function itemPayload(string $itemId): array
    {
        $body = json_decode((string) $this->client->getResponse()->getContent(), true);
        foreach ($body['items'] as $payload) {
            if ($payload['id'] === $itemId) {
                return $payload;
            }
        }

        self::fail(sprintf('Item %s is not in the rider payload.', $itemId));
    }

    private function membershipRepository(): \App\Repository\BandSpace\BandSpaceMembershipRepository
    {
        return self::getContainer()->get(\App\Repository\BandSpace\BandSpaceMembershipRepository::class);
    }

    private function itemUrl(BandSpace $bandSpace, TechRider $rider, TechRiderItem $item): string
    {
        return $this->riderUrl($bandSpace, $rider) . '/items/' . $item->id;
    }

    private function riderUrl(BandSpace $bandSpace, TechRider $rider): string
    {
        return '/api/band_spaces/' . $bandSpace->id . '/tech_riders/' . $rider->id;
    }
}
