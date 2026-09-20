<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Chat;

use App\Entity\BandSpace\BandSpace;
use App\Entity\Message\Message;
use App\Entity\Message\MessageThread;
use App\Entity\User;
use App\Enum\BandSpace\MembershipStatus;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\Message\MessageFactory;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class ChatPinnedMessageGetCollectionTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_not_logged(): void
    {
        $space = BandSpaceFactory::new()->create();

        $this->client->request('GET', $this->url($space));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    public function test_the_pinned_messages_come_back_newest_pin_first(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $channel = $this->channelOf($space);

        // The older message carries the newer pin, so the order can only come from pinned_datetime.
        $pinnedLast = $this->message($channel, $member, 'adresse de la salle', '2026-09-10 20:00:00', '2026-09-12 18:00:00', $member);
        $pinnedFirst = $this->message($channel, $member, 'code de la porte 4512', '2026-09-11 20:00:00', '2026-09-11 09:00:00', $member);
        $this->message($channel, $member, 'on répète mardi', '2026-09-11 21:00:00');

        $this->client->loginUser($member);
        $this->client->request('GET', $this->url($space));

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ChatMessage',
            '@id' => $this->url($space),
            '@type' => 'Collection',
            'totalItems' => 2,
            'member' => [
                $this->expectedMessage($pinnedLast, $space, $member, 'batteur', 'adresse de la salle', '2026-09-10T20:00:00+00:00', '2026-09-12T18:00:00+00:00', 'batteur'),
                $this->expectedMessage($pinnedFirst, $space, $member, 'batteur', 'code de la porte 4512', '2026-09-11T20:00:00+00:00', '2026-09-11T09:00:00+00:00', 'batteur'),
            ],
        ]);
    }

    public function test_only_this_band_channel_is_listed(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create(['name' => 'Les Trois Accords']);
        $otherSpace = BandSpaceFactory::new()->create(['name' => 'Autre groupe']);
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $otherSpace, 'user' => $member])->create();

        $mine = $this->message($this->channelOf($space), $member, 'notre code', '2026-09-10 20:00:00', '2026-09-11 09:00:00', $member);
        $this->message($this->channelOf($otherSpace), $member, 'leur code', '2026-09-10 20:00:00', '2026-09-11 10:00:00', $member);

        $this->client->loginUser($member);
        $this->client->request('GET', $this->url($space));

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ChatMessage',
            '@id' => $this->url($space),
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                $this->expectedMessage($mine, $space, $member, 'batteur', 'notre code', '2026-09-10T20:00:00+00:00', '2026-09-11T09:00:00+00:00', 'batteur'),
            ],
        ]);
    }

    public function test_a_channel_with_nothing_pinned_is_empty(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $this->message($this->channelOf($space), $member, 'on répète mardi', '2026-09-10 20:00:00');

        $this->client->loginUser($member);
        $this->client->request('GET', $this->url($space));

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ChatMessage',
            '@id' => $this->url($space),
            '@type' => 'Collection',
            'totalItems' => 0,
            'member' => [],
        ]);
    }

    public function test_a_pin_by_a_member_who_has_left_still_names_them(): void
    {
        // The pin survives the member, exactly as their messages do, and the bar keeps saying who put
        // it there rather than going blank on the useful information.
        $gone = UserFactory::new()->asBaseUser()->create(['username' => 'chanteuse', 'email' => 'chanteuse@test.com']);
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new([
            'bandSpace' => $space,
            'user' => $gone,
            'status' => MembershipStatus::Left,
        ])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $message = $this->message($this->channelOf($space), $member, 'code de la porte 4512', '2026-09-10 20:00:00', '2026-09-11 09:00:00', $gone);

        $this->client->loginUser($member);
        $this->client->request('GET', $this->url($space));

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ChatMessage',
            '@id' => $this->url($space),
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                $this->expectedMessage($message, $space, $member, 'batteur', 'code de la porte 4512', '2026-09-10T20:00:00+00:00', '2026-09-11T09:00:00+00:00', 'chanteuse'),
            ],
        ]);
    }

    public function test_a_pin_by_a_deleted_account_is_not_named(): void
    {
        // DeleteAccountProcedure rewrites the handle to `deleted_<uuid>` and keeps the row, so the
        // relation stays valid and the renderer must substitute the name, like it does for an author.
        $gone = UserFactory::new()->asBaseUser()->create([
            'username' => 'deleted_3f2504e0',
            'email' => 'gone@test.com',
            'deletionDatetime' => new \DateTimeImmutable('2026-09-01 10:00:00'),
        ]);
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $message = $this->message($this->channelOf($space), $member, 'code de la porte 4512', '2026-09-10 20:00:00', '2026-09-11 09:00:00', $gone);

        $this->client->loginUser($member);
        $this->client->request('GET', $this->url($space));

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ChatMessage',
            '@id' => $this->url($space),
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                $this->expectedMessage($message, $space, $member, 'batteur', 'code de la porte 4512', '2026-09-10T20:00:00+00:00', '2026-09-11T09:00:00+00:00', 'Utilisateur supprimé'),
            ],
        ]);
    }

    public function test_a_space_pending_deletion_still_lists_its_pinned_messages(): void
    {
        // Reading stays open for the whole grace period, and the pinned bar is where the addresses and
        // codes worth retrieving are, so this provider uses checkMember() rather than the write variant.
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create(['deletionScheduledDatetime' => new \DateTimeImmutable('+30 days')]);
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $message = $this->message($this->channelOf($space), $member, 'code de la porte 4512', '2026-09-10 20:00:00', '2026-09-11 09:00:00', $member);

        $this->client->loginUser($member);
        $this->client->request('GET', $this->url($space));

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ChatMessage',
            '@id' => $this->url($space),
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                $this->expectedMessage($message, $space, $member, 'batteur', 'code de la porte 4512', '2026-09-10T20:00:00+00:00', '2026-09-11T09:00:00+00:00', 'batteur'),
            ],
        ]);
    }

    public function test_the_pinned_list_never_loads_a_profile_table_per_pinner(): void
    {
        // The bar reads the same projection as the message list, so it inherits the rule that made
        // that projection necessary: `User` carries three inverse one-to-one profile associations
        // Doctrine cannot make lazy (#730), and the pinner is a second user per row. Ten distinct
        // pinners looked up one at a time would be thirty selects for a bar of ten lines.
        //
        // Exactly one membership is seeded, so checkMember() hydrating the roster stays a constant
        // and what is left to measure is the pin join alone.
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $channel = $this->channelOf($space);

        foreach (range(1, 10) as $index) {
            $pinner = UserFactory::new()->asBaseUser()->create([
                'username' => 'epingleur_' . $index,
                'email' => 'epingleur' . $index . '@test.com',
            ]);
            $this->message(
                $channel,
                $pinner,
                'info ' . $index,
                sprintf('2026-09-10 20:%02d:00', $index),
                sprintf('2026-09-11 09:%02d:00', $index),
                $pinner,
            );
        }

        $this->client->loginUser($member);
        $this->client->enableProfiler();
        // The factories above left every user managed, which would hide the very lazy loads this test
        // exists to count.
        self::getContainer()->get('doctrine')->getManager()->clear();
        self::getContainer()->get('doctrine.debug_data_holder')->reset();
        $this->client->request('GET', $this->url($space));

        $this->assertResponseIsSuccessful();
        foreach (['user_musician_profile', 'user_notification_preference', 'user_teacher_profile'] as $profileTable) {
            // At most two: the firewall hydrates the viewer before the provider runs, and checkMember()
            // hydrates the one membership. Ten pinners fetched one at a time would be twelve here.
            $this->assertLessThanOrEqual(
                2,
                count($this->queriesMatching($profileTable)),
                sprintf('Listing the pinned messages must not hydrate their pinners, and %s says it did', $profileTable),
            );
        }

        // And the request does not grow with the number of distinct pinners: measured at 11, the
        // pinned projection, the mention lookup and the read positions (#977) included. A measurement
        // rather than a sum, since what a request costs drifts as features land. The margin is for a
        // change to the firewall, not for a per-pinner query, which would put this past twenty.
        $this->assertLessThanOrEqual(
            12,
            $this->client->getProfile()->getCollector('db')->getQueryCount(),
            'The pinned list must cost a fixed number of queries whatever the pinner count',
        );
    }

    public function test_a_non_member_cannot_read_the_pinned_messages(): void
    {
        $stranger = UserFactory::new()->asBaseUser()->create(['username' => 'inconnu', 'email' => 'inconnu@test.com']);
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $this->channelOf($space);

        $this->client->loginUser($stranger);
        $this->client->request('GET', $this->url($space));

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Vous n\'êtes pas membre de ce Band Space',
            'description' => 'Vous n\'êtes pas membre de ce Band Space',
            'status' => 403,
            'type' => '/errors/403',
        ]);
    }

    public function test_a_band_space_that_does_not_exist_is_a_404(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/band_spaces/3f2504e0-4f89-11d3-9a0c-0305e82c3301/chat/pinned_messages');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Band Space introuvable',
            'description' => 'Band Space introuvable',
            'status' => 404,
            'type' => '/errors/404',
        ]);
    }

    public function test_a_band_space_without_a_channel_is_a_404(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();

        $this->client->loginUser($member);
        $this->client->request('GET', $this->url($space));

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Ce Band Space n\'a pas de conversation',
            'description' => 'Ce Band Space n\'a pas de conversation',
            'status' => 404,
            'type' => '/errors/404',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function expectedMessage(
        Message $message,
        BandSpace $space,
        User $author,
        string $expectedUsername,
        string $content,
        string $creationDatetime,
        string $pinnedDatetime,
        string $pinnedByUsername,
    ): array {
        return [
            '@id' => '/api/chat_messages/id=' . $message->id . ';bandSpaceId=' . $space->id,
            '@type' => 'ChatMessage',
            'id' => (string) $message->id,
            'band_space_id' => (string) $space->id,
            'author_id' => (string) $author->id,
            'author_username' => $expectedUsername,
            'author_profile_picture_url' => null,
            'content' => $content,
            'creation_datetime' => $creationDatetime,
            'reactions' => [],
            'attachments' => [],
            'update_datetime' => null,
            // Every case here is read by the member who wrote the message, so the raw text comes back
            // for the edit box (#966).
            'editable_content' => $content,
            'is_deleted' => false,
            'is_pinned' => true,
            'pinned_datetime' => $pinnedDatetime,
            'pinned_by_username' => $pinnedByUsername,
            'read_by_usernames' => [],
            'read_count' => 0,
        ];
    }

    private function channelOf(BandSpace $bandSpace): MessageThread
    {
        return MessageThreadFactory::new()->forBandSpace($bandSpace)->create();
    }

    private function message(
        MessageThread $channel,
        User $author,
        string $content,
        string $creationDatetime,
        ?string $pinnedDatetime = null,
        ?User $pinnedBy = null,
    ): Message {
        return MessageFactory::new([
            'thread' => $channel,
            'author' => $author,
            'content' => $content,
            'creationDatetime' => new \DateTime($creationDatetime),
            'pinnedDatetime' => $pinnedDatetime === null ? null : new \DateTimeImmutable($pinnedDatetime),
            'pinnedBy' => $pinnedBy,
        ])->create();
    }

    private function url(BandSpace $bandSpace): string
    {
        return '/api/band_spaces/' . $bandSpace->id . '/chat/pinned_messages';
    }

    /**
     * @return list<string>
     */
    private function queriesMatching(string $needle): array
    {
        $profile = $this->client->getProfile();
        $this->assertNotFalse($profile, 'The profiler must be enabled to inspect the queries.');

        $matching = [];
        foreach ($profile->getCollector('db')->getQueries()['default'] ?? [] as $query) {
            $sql = (string) $query['sql'];
            if (str_contains($sql, $needle)) {
                $matching[] = $sql;
            }
        }

        return $matching;
    }
}
