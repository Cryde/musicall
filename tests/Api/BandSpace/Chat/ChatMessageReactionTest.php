<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Chat;

use App\Entity\BandSpace\BandSpace;
use App\Entity\Message\Message;
use App\Entity\Message\MessageThread;
use App\Entity\User;
use App\Enum\BandSpace\MembershipStatus;
use App\Enum\Message\MessageReactionEmoji;
use App\Repository\Message\MessageReactionRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\Message\MessageFactory;
use App\Tests\Factory\Message\MessageReactionFactory;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class ChatMessageReactionTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_not_logged(): void
    {
        $space = BandSpaceFactory::new()->create();
        $channel = $this->channelOf($space);
        $message = $this->messageIn($channel, UserFactory::new()->asBaseUser()->create());

        $this->react($space, $message, 'thumbs_up');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    public function test_reacting_to_a_message(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $message = $this->messageIn($this->channelOf($space), $member);

        $this->client->loginUser($member);
        $this->react($space, $message, 'thumbs_up');

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ChatMessage',
            '@id' => '/api/chat_messages/id=' . $message->id . ';bandSpaceId=' . $space->id,
            '@type' => 'ChatMessage',
            'id' => (string) $message->id,
            'band_space_id' => (string) $space->id,
            'author_id' => (string) $member->id,
            'author_username' => 'batteur',
            'author_profile_picture_url' => null,
            'content' => 'on répète mardi',
            'creation_datetime' => '2026-09-10T20:00:00+00:00',
            'update_datetime' => null,
            'editable_content' => 'on répète mardi',
            'attachments' => [],
            'reactions' => [
                ['key' => 'thumbs_up', 'emoji' => '👍', 'count' => 1, 'has_reacted' => true],
            ],
        ]);
        $this->assertSame(1, $this->countReactions());
    }

    public function test_reacting_twice_with_the_same_emoji_changes_nothing(): void
    {
        // A double tap on a phone, and the reason this is not a toggle endpoint: the second call must
        // leave the reaction where the first one put it rather than quietly take it back.
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $message = $this->messageIn($this->channelOf($space), $member);
        MessageReactionFactory::new(['message' => $message, 'user' => $member, 'emoji' => MessageReactionEmoji::ThumbsUp])->create();

        $this->client->loginUser($member);
        $this->react($space, $message, 'thumbs_up');

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ChatMessage',
            '@id' => '/api/chat_messages/id=' . $message->id . ';bandSpaceId=' . $space->id,
            '@type' => 'ChatMessage',
            'id' => (string) $message->id,
            'band_space_id' => (string) $space->id,
            'author_id' => (string) $member->id,
            'author_username' => 'batteur',
            'author_profile_picture_url' => null,
            'content' => 'on répète mardi',
            'creation_datetime' => '2026-09-10T20:00:00+00:00',
            'update_datetime' => null,
            'editable_content' => 'on répète mardi',
            'attachments' => [],
            'reactions' => [
                ['key' => 'thumbs_up', 'emoji' => '👍', 'count' => 1, 'has_reacted' => true],
            ],
        ]);
        $this->assertSame(1, $this->countReactions());
    }

    public function test_removing_a_reaction(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $message = $this->messageIn($this->channelOf($space), $member);
        MessageReactionFactory::new(['message' => $message, 'user' => $member, 'emoji' => MessageReactionEmoji::Heart])->create();

        $this->client->loginUser($member);
        $this->unreact($space, $message, 'heart');

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame('', $this->client->getResponse()->getContent());
        $this->assertSame(0, $this->countReactions());
    }

    public function test_removing_only_takes_back_the_callers_own_reaction(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $other = UserFactory::new()->asBaseUser()->create(['username' => 'bassiste', 'email' => 'bassiste@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $other])->create();
        $message = $this->messageIn($this->channelOf($space), $member);
        MessageReactionFactory::new(['message' => $message, 'user' => $member, 'emoji' => MessageReactionEmoji::Heart])->create();
        MessageReactionFactory::new(['message' => $message, 'user' => $other, 'emoji' => MessageReactionEmoji::Heart])->create();

        $this->client->loginUser($member);
        $this->unreact($space, $message, 'heart');

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame(1, $this->countReactions());
    }

    public function test_removing_a_reaction_never_left_is_a_404(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $message = $this->messageIn($this->channelOf($space), $member);

        $this->client->loginUser($member);
        $this->unreact($space, $message, 'fire');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Réaction introuvable',
            'description' => 'Réaction introuvable',
            'status' => 404,
            'type' => '/errors/404',
        ]);
    }

    public function test_an_emoji_outside_the_allow_list_is_refused(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $message = $this->messageIn($this->channelOf($space), $member);

        $this->client->loginUser($member);
        $this->react($space, $message, 'aubergine');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/8e179f1b-97aa-4560-a02f-2a8b42e49df7',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'emoji',
                    'message' => 'Réaction inconnue',
                    'code' => '8e179f1b-97aa-4560-a02f-2a8b42e49df7',
                ],
            ],
            'detail' => 'emoji: Réaction inconnue',
            'type' => '/validation_errors/8e179f1b-97aa-4560-a02f-2a8b42e49df7',
            'title' => 'An error occurred',
            'description' => 'emoji: Réaction inconnue',
        ]);
        $this->assertSame(0, $this->countReactions());
    }

    public function test_an_emoji_outside_the_allow_list_cannot_be_removed_either(): void
    {
        // The remove path takes the slug from the URL, where there is nothing for the validator to
        // hold: a slug nobody can leave names a reaction that cannot exist, so it is the same 404 as
        // one the member never left.
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $message = $this->messageIn($this->channelOf($space), $member);

        $this->client->loginUser($member);
        $this->unreact($space, $message, 'aubergine');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Réaction introuvable',
            'description' => 'Réaction introuvable',
            'status' => 404,
            'type' => '/errors/404',
        ]);
    }

    public function test_a_non_member_cannot_react(): void
    {
        $stranger = UserFactory::new()->asBaseUser()->create(['username' => 'inconnu', 'email' => 'inconnu@test.com']);
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $message = $this->messageIn($this->channelOf($space), $member);

        $this->client->loginUser($stranger);
        $this->react($space, $message, 'thumbs_up');

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
        $this->assertSame(0, $this->countReactions());
    }

    public function test_a_message_that_does_not_exist_is_a_404(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $this->channelOf($space);

        $this->client->loginUser($member);
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $space->id . '/chat/messages/3f2504e0-4f89-11d3-9a0c-0305e82c3301/reactions',
            ['emoji' => 'thumbs_up'],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Message introuvable',
            'description' => 'Message introuvable',
            'status' => 404,
            'type' => '/errors/404',
        ]);
    }

    public function test_a_message_from_another_bands_channel_is_a_404(): void
    {
        // The message id is real, the band in the path is not the one holding it. Scoping the lookup
        // to the space is what stops one band reacting to another band's conversation.
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $mine = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $mine, 'user' => $member])->create();
        $this->channelOf($mine);

        $theirs = BandSpaceFactory::new()->create();
        $theirMessage = $this->messageIn($this->channelOf($theirs), $member);

        $this->client->loginUser($member);
        $this->react($mine, $theirMessage, 'thumbs_up');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Message introuvable',
            'description' => 'Message introuvable',
            'status' => 404,
            'type' => '/errors/404',
        ]);
        $this->assertSame(0, $this->countReactions());
    }

    public function test_a_space_pending_deletion_refuses_a_reaction(): void
    {
        // Reading stays open for the whole grace period, writing does not. This falls out of the
        // processor using checkMemberForWrite().
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create([
            'deletionScheduledDatetime' => new \DateTimeImmutable('+30 days'),
        ]);
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $message = $this->messageIn($this->channelOf($space), $member);

        $this->client->loginUser($member);
        $this->react($space, $message, 'thumbs_up');

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Cet espace est en attente de suppression, les modifications sont désactivées',
            'description' => 'Cet espace est en attente de suppression, les modifications sont désactivées',
            'status' => 409,
            'type' => '/errors/409',
        ]);
    }

    public function test_a_space_pending_deletion_refuses_taking_a_reaction_back(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create([
            'deletionScheduledDatetime' => new \DateTimeImmutable('+30 days'),
        ]);
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $message = $this->messageIn($this->channelOf($space), $member);
        MessageReactionFactory::new(['message' => $message, 'user' => $member, 'emoji' => MessageReactionEmoji::Fire])->create();

        $this->client->loginUser($member);
        $this->unreact($space, $message, 'fire');

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Cet espace est en attente de suppression, les modifications sont désactivées',
            'description' => 'Cet espace est en attente de suppression, les modifications sont désactivées',
            'status' => 409,
            'type' => '/errors/409',
        ]);
        $this->assertSame(1, $this->countReactions());
    }

    public function test_the_list_aggregates_the_counts_and_marks_the_viewers_own(): void
    {
        $viewer = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $second = UserFactory::new()->asBaseUser()->create(['username' => 'bassiste', 'email' => 'bassiste@test.com']);
        $third = UserFactory::new()->asBaseUser()->create(['username' => 'chanteur', 'email' => 'chanteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        foreach ([$viewer, $second, $third] as $user) {
            BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $user])->create();
        }
        $message = $this->messageIn($this->channelOf($space), $viewer);

        MessageReactionFactory::new(['message' => $message, 'user' => $viewer, 'emoji' => MessageReactionEmoji::ThumbsUp])->create();
        MessageReactionFactory::new(['message' => $message, 'user' => $second, 'emoji' => MessageReactionEmoji::ThumbsUp])->create();
        MessageReactionFactory::new(['message' => $message, 'user' => $third, 'emoji' => MessageReactionEmoji::Heart])->create();

        $this->client->loginUser($viewer);
        $this->client->request('GET', '/api/band_spaces/' . $space->id . '/chat/messages');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ChatMessage',
            '@id' => '/api/band_spaces/' . $space->id . '/chat/messages',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                [
                    '@id' => '/api/chat_messages/id=' . $message->id . ';bandSpaceId=' . $space->id,
                    '@type' => 'ChatMessage',
                    'id' => (string) $message->id,
                    'band_space_id' => (string) $space->id,
                    'author_id' => (string) $viewer->id,
                    'author_username' => 'batteur',
                    'author_profile_picture_url' => null,
                    'content' => 'on répète mardi',
                    'creation_datetime' => '2026-09-10T20:00:00+00:00',
                    'update_datetime' => null,
                    'editable_content' => 'on répète mardi',
                    'attachments' => [],
                    // Two members on the thumb, one on the heart, and every other emoji absent rather
                    // than present with a zero.
                    'reactions' => [
                        ['key' => 'thumbs_up', 'emoji' => '👍', 'count' => 2, 'has_reacted' => true],
                        ['key' => 'heart', 'emoji' => '❤️', 'count' => 1, 'has_reacted' => false],
                    ],
                ],
            ],
        ]);
    }

    public function test_the_list_orders_the_reactions_the_way_the_enum_declares_them(): void
    {
        // Inserted backwards on purpose: the order has to come from the enum, not from whatever the
        // database hands back.
        $viewer = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $viewer])->create();
        $message = $this->messageIn($this->channelOf($space), $viewer);

        foreach ([MessageReactionEmoji::Check, MessageReactionEmoji::Guitar, MessageReactionEmoji::ThumbsDown] as $emoji) {
            MessageReactionFactory::new(['message' => $message, 'user' => $viewer, 'emoji' => $emoji])->create();
        }

        $this->client->loginUser($viewer);
        $this->client->request('GET', '/api/band_spaces/' . $space->id . '/chat/messages');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ChatMessage',
            '@id' => '/api/band_spaces/' . $space->id . '/chat/messages',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                [
                    '@id' => '/api/chat_messages/id=' . $message->id . ';bandSpaceId=' . $space->id,
                    '@type' => 'ChatMessage',
                    'id' => (string) $message->id,
                    'band_space_id' => (string) $space->id,
                    'author_id' => (string) $viewer->id,
                    'author_username' => 'batteur',
                    'author_profile_picture_url' => null,
                    'content' => 'on répète mardi',
                    'creation_datetime' => '2026-09-10T20:00:00+00:00',
                    'update_datetime' => null,
                    'editable_content' => 'on répète mardi',
                    'attachments' => [],
                    'reactions' => [
                        ['key' => 'thumbs_down', 'emoji' => '👎', 'count' => 1, 'has_reacted' => true],
                        ['key' => 'guitar', 'emoji' => '🎸', 'count' => 1, 'has_reacted' => true],
                        ['key' => 'check', 'emoji' => '✅', 'count' => 1, 'has_reacted' => true],
                    ],
                ],
            ],
        ]);
    }

    public function test_a_former_members_reaction_still_counts(): void
    {
        // Their messages stay, so their reactions stay: the count on an old message records what the
        // band thought at the time, and rewriting it when somebody walks away would falsify history.
        $viewer = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $gone = UserFactory::new()->asBaseUser()->create(['username' => 'parti', 'email' => 'parti@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $viewer])->create();
        BandSpaceMembershipFactory::new([
            'bandSpace' => $space,
            'user' => $gone,
            'status' => MembershipStatus::Left,
            'leftDatetime' => new \DateTime('2026-09-11 09:00:00'),
        ])->create();
        $message = $this->messageIn($this->channelOf($space), $viewer);
        MessageReactionFactory::new(['message' => $message, 'user' => $gone, 'emoji' => MessageReactionEmoji::Party])->create();

        $this->client->loginUser($viewer);
        $this->client->request('GET', '/api/band_spaces/' . $space->id . '/chat/messages');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ChatMessage',
            '@id' => '/api/band_spaces/' . $space->id . '/chat/messages',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                [
                    '@id' => '/api/chat_messages/id=' . $message->id . ';bandSpaceId=' . $space->id,
                    '@type' => 'ChatMessage',
                    'id' => (string) $message->id,
                    'band_space_id' => (string) $space->id,
                    'author_id' => (string) $viewer->id,
                    'author_username' => 'batteur',
                    'author_profile_picture_url' => null,
                    'content' => 'on répète mardi',
                    'creation_datetime' => '2026-09-10T20:00:00+00:00',
                    'update_datetime' => null,
                    'editable_content' => 'on répète mardi',
                    'attachments' => [],
                    'reactions' => [
                        ['key' => 'party', 'emoji' => '🎉', 'count' => 1, 'has_reacted' => false],
                    ],
                ],
            ],
        ]);
    }

    public function test_the_reaction_aggregate_costs_one_query_for_the_whole_page(): void
    {
        // The trap the issue names: a page counting its own reactions message by message is the same
        // N+1 class the author projection exists to avoid. Twelve messages carrying three reactions
        // each would be twelve extra queries, and hydrating the reacting users on top would be far
        // more, since User force-loads three profile tables (#730).
        $viewer = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $viewer])->create();
        $channel = $this->channelOf($space);

        $reactors = [];
        foreach (range(1, 3) as $index) {
            $reactors[] = UserFactory::new()->asBaseUser()->create([
                'username' => 'reacteur_' . $index,
                'email' => 'reacteur' . $index . '@test.com',
            ]);
        }

        foreach (range(1, 12) as $index) {
            $message = MessageFactory::new([
                'thread' => $channel,
                'author' => $viewer,
                'content' => 'message ' . $index,
                'creationDatetime' => new \DateTime(sprintf('2026-09-10 20:%02d:00', $index)),
            ])->create();

            foreach ($reactors as $position => $reactor) {
                MessageReactionFactory::new([
                    'message' => $message,
                    'user' => $reactor,
                    'emoji' => MessageReactionEmoji::cases()[$position],
                ])->create();
            }
        }

        $this->client->loginUser($viewer);
        $this->client->enableProfiler();
        // The factories above left everything managed, which would hide the very lazy loads this test
        // exists to count.
        self::getContainer()->get('doctrine')->getManager()->clear();
        self::getContainer()->get('doctrine.debug_data_holder')->reset();
        $this->client->request('GET', '/api/band_spaces/' . $space->id . '/chat/messages');

        $this->assertResponseIsSuccessful();
        $this->assertCount(3, $this->getResponseAsArray()['member'][0]['reactions']);

        foreach (['user_musician_profile', 'user_notification_preference', 'user_teacher_profile'] as $profileTable) {
            // At most one, and it is the authenticated viewer the firewall loads before the provider
            // runs. Hydrating the three reacting users would put this at four.
            $this->assertLessThanOrEqual(
                1,
                count($this->queriesMatching($profileTable)),
                sprintf('Aggregating reactions must not hydrate who left them, and %s says it did', $profileTable),
            );
        }

        // Measured at 9: the eight the page already cost before reactions existed, plus the one
        // grouped aggregate. The margin is for a change to the firewall, not for a per-message query,
        // which would put this past twenty.
        $this->assertLessThanOrEqual(
            11,
            $this->client->getProfile()->getCollector('db')->getQueryCount(),
            'The reaction aggregate must cost a fixed number of queries whatever the page holds',
        );
    }

    private function channelOf(BandSpace $bandSpace): MessageThread
    {
        return MessageThreadFactory::new()->forBandSpace($bandSpace)->create();
    }

    private function messageIn(MessageThread $channel, User $author): Message
    {
        return MessageFactory::new([
            'thread' => $channel,
            'author' => $author,
            'content' => 'on répète mardi',
            'creationDatetime' => new \DateTime('2026-09-10 20:00:00'),
        ])->create();
    }

    private function react(BandSpace $bandSpace, Message $message, string $emoji): void
    {
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/chat/messages/' . $message->id . '/reactions',
            ['emoji' => $emoji],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );
    }

    private function unreact(BandSpace $bandSpace, Message $message, string $emoji): void
    {
        $this->client->request(
            'DELETE',
            '/api/band_spaces/' . $bandSpace->id . '/chat/messages/' . $message->id . '/reactions/' . $emoji,
            [],
            [],
            ['HTTP_ACCEPT' => 'application/ld+json'],
        );
    }

    private function countReactions(): int
    {
        return self::getContainer()->get(MessageReactionRepository::class)->count([]);
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
