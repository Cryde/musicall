<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Chat;

use App\Entity\BandSpace\BandSpace;
use App\Entity\Message\Message;
use App\Entity\Message\MessageThread;
use App\Entity\User;
use App\Enum\BandSpace\MembershipStatus;
use App\Enum\BandSpace\Role;
use App\Repository\Message\MessageAttachmentRepository;
use App\Repository\Message\MessageMentionRepository;
use App\Repository\Message\MessageRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\Message\MessageAttachmentFactory;
use App\Tests\Factory\Message\MessageFactory;
use App\Tests\Factory\Message\MessageMentionFactory;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * Deleting a chat message (#967). A tombstone, not a hole: the row survives with its author, its time
 * and an empty content, which is what keeps the conversation around it readable.
 */
#[ResetDatabase]
class ChatMessageDeleteTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_not_logged(): void
    {
        $space = BandSpaceFactory::new()->create();

        $this->delete($space, '3f2504e0-4f89-11d3-9a0c-0305e82c3301');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    public function test_the_author_deletes_their_own_message(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $bassist = UserFactory::new()->asBaseUser()->create(['username' => 'bassiste', 'email' => 'bassiste@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $bassist])->create();
        $channel = $this->channelOf($space);

        $message = MessageFactory::new([
            'thread' => $channel,
            'author' => $member,
            'content' => 'salut @[' . $bassist->id . ']',
            'creationDatetime' => new \DateTime('2026-09-10 20:00:00'),
        ])->create();
        MessageMentionFactory::new(['message' => $message, 'mentionedUser' => $bassist])->create();
        $messageId = (string) $message->id;

        $this->client->loginUser($member);
        $this->delete($space, $messageId);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame('', $this->client->getResponse()->getContent());

        $tombstone = self::getContainer()->get(MessageRepository::class)->find($messageId);
        $this->assertInstanceOf(Message::class, $tombstone, 'A delete leaves the row behind');
        $this->assertSame('', $tombstone->content, 'The content is really gone, not merely flagged');
        $this->assertNotNull($tombstone->deletionDatetime);
        // Authorship and the time stay: they are what the tombstone still says.
        $this->assertSame($member->id, $tombstone->author->id);
        $this->assertSame('2026-09-10 20:00:00', $tombstone->creationDatetime->format('Y-m-d H:i:s'));
        $this->assertSame(
            [],
            self::getContainer()->get(MessageMentionRepository::class)->findBy(['message' => $messageId]),
            'The mention rows go with the content they pointed into',
        );
    }

    public function test_a_delete_clears_what_the_message_pointed_at(): void
    {
        // Same rule as the mention rows above: an attachment names a task or a file, so leaving it
        // behind would keep serving that in the payload of a message whose content is gone (#970).
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $channel = $this->channelOf($space);

        $message = MessageFactory::new([
            'thread' => $channel,
            'author' => $member,
            'content' => 'regarde ça',
            'creationDatetime' => new \DateTime('2026-09-10 20:00:00'),
        ])->create();
        MessageAttachmentFactory::new(['message' => $message, 'label' => 'Réparer l\'ampli'])->create();
        $messageId = (string) $message->id;

        $this->client->loginUser($member);
        $this->delete($space, $messageId);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame(
            [],
            self::getContainer()->get(MessageAttachmentRepository::class)->findByMessageIds([$messageId]),
            'The attachment rows go with the content they hung off',
        );
    }

    public function test_a_delete_takes_the_message_out_of_the_pinned_bar(): void
    {
        // A pin says « keep this at the top », and what it pointed at has just gone (#969). Leaving it
        // would put « Message supprimé » in the « infos importantes » bar.
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();

        $message = MessageFactory::new([
            'thread' => $this->channelOf($space),
            'author' => $member,
            'content' => 'code de la porte 4512',
            'creationDatetime' => new \DateTime('2026-09-10 20:00:00'),
            'pinnedDatetime' => new \DateTimeImmutable('2026-09-11 09:00:00'),
            'pinnedBy' => $member,
        ])->create();
        $messageId = (string) $message->id;

        $this->client->loginUser($member);
        $this->delete($space, $messageId);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $tombstone = self::getContainer()->get(MessageRepository::class)->find($messageId);
        $this->assertInstanceOf(Message::class, $tombstone);
        $this->assertNull($tombstone->pinnedDatetime, 'A tombstone is no longer pinned');
        $this->assertNull($tombstone->pinnedBy);
    }

    public function test_an_admin_deletes_another_members_message(): void
    {
        $author = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $admin = UserFactory::new()->asBaseUser()->create(['username' => 'chanteuse', 'email' => 'chanteuse@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $author])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $admin, 'role' => Role::Admin])->create();
        $channel = $this->channelOf($space);

        $message = MessageFactory::new([
            'thread' => $channel,
            'author' => $author,
            'content' => 'un message à modérer',
            'creationDatetime' => new \DateTime('2026-09-10 20:00:00'),
        ])->create();
        $messageId = (string) $message->id;

        $this->client->loginUser($admin);
        $this->delete($space, $messageId);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $tombstone = self::getContainer()->get(MessageRepository::class)->find($messageId);
        $this->assertInstanceOf(Message::class, $tombstone);
        $this->assertSame('', $tombstone->content);
        $this->assertNotNull($tombstone->deletionDatetime);
    }

    public function test_an_admin_deletes_a_former_members_message(): void
    {
        // Concern 1 of #948: leaving the band does not take your messages with you, and taking one
        // down afterwards is an administrator moderating, not a side effect of the departure.
        $gone = UserFactory::new()->asBaseUser()->create(['username' => 'ancien', 'email' => 'ancien@test.com']);
        $admin = UserFactory::new()->asBaseUser()->create(['username' => 'chanteuse', 'email' => 'chanteuse@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new([
            'bandSpace' => $space,
            'user' => $gone,
            'status' => MembershipStatus::Left,
        ])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $admin, 'role' => Role::Admin])->create();
        $channel = $this->channelOf($space);

        $message = MessageFactory::new([
            'thread' => $channel,
            'author' => $gone,
            'content' => 'je pars',
            'creationDatetime' => new \DateTime('2026-09-10 20:00:00'),
        ])->create();
        $messageId = (string) $message->id;

        $this->client->loginUser($admin);
        $this->delete($space, $messageId);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $tombstone = self::getContainer()->get(MessageRepository::class)->find($messageId);
        $this->assertInstanceOf(Message::class, $tombstone);
        $this->assertSame('', $tombstone->content);
        $this->assertSame($gone->id, $tombstone->author->id, 'Authorship survives the delete');
    }

    public function test_a_plain_member_cannot_delete_another_members_message(): void
    {
        $author = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $other = UserFactory::new()->asBaseUser()->create(['username' => 'bassiste', 'email' => 'bassiste@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $author])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $other])->create();
        $channel = $this->channelOf($space);

        $message = MessageFactory::new([
            'thread' => $channel,
            'author' => $author,
            'content' => 'on répète mardi',
            'creationDatetime' => new \DateTime('2026-09-10 20:00:00'),
        ])->create();
        $messageId = (string) $message->id;

        $this->client->loginUser($other);
        $this->delete($space, $messageId);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Seul l\'auteur ou un administrateur peut supprimer ce message',
            'description' => 'Seul l\'auteur ou un administrateur peut supprimer ce message',
            'status' => 403,
            'type' => '/errors/403',
        ]);

        $untouched = self::getContainer()->get(MessageRepository::class)->find($messageId);
        $this->assertInstanceOf(Message::class, $untouched);
        $this->assertSame('on répète mardi', $untouched->content);
        $this->assertNull($untouched->deletionDatetime);
    }

    public function test_a_non_member_cannot_delete(): void
    {
        $stranger = UserFactory::new()->asBaseUser()->create(['username' => 'inconnu', 'email' => 'inconnu@test.com']);
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $channel = $this->channelOf($space);

        $message = MessageFactory::new([
            'thread' => $channel,
            'author' => $member,
            'content' => 'on répète mardi',
            'creationDatetime' => new \DateTime('2026-09-10 20:00:00'),
        ])->create();

        $this->client->loginUser($stranger);
        $this->delete($space, (string) $message->id);

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

    public function test_an_unknown_message_is_a_404(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $this->channelOf($space);

        $this->client->loginUser($member);
        $this->delete($space, '3f2504e0-4f89-11d3-9a0c-0305e82c3301');

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

    public function test_a_message_of_another_spaces_channel_is_a_404(): void
    {
        // A member of both, so this cannot pass by accident on the membership check: the lookup is
        // scoped to the channel named in the URL, and a message of the other one is simply not there.
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        $otherSpace = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $otherSpace, 'user' => $member])->create();
        $this->channelOf($space);
        $otherChannel = $this->channelOf($otherSpace);

        $elsewhere = MessageFactory::new([
            'thread' => $otherChannel,
            'author' => $member,
            'content' => 'dans l\'autre groupe',
            'creationDatetime' => new \DateTime('2026-09-10 20:00:00'),
        ])->create();
        $messageId = (string) $elsewhere->id;

        $this->client->loginUser($member);
        $this->delete($space, $messageId);

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

        $untouched = self::getContainer()->get(MessageRepository::class)->find($messageId);
        $this->assertInstanceOf(Message::class, $untouched);
        $this->assertNull($untouched->deletionDatetime);
    }

    public function test_an_already_deleted_message_is_a_404(): void
    {
        // Deleting a tombstone is a miss, not a second delete, so a double click cannot restamp the
        // date. The author asks here, which is the case a 403 would have hidden.
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $channel = $this->channelOf($space);

        $message = MessageFactory::new([
            'thread' => $channel,
            'author' => $member,
            'content' => '',
            'creationDatetime' => new \DateTime('2026-09-10 20:00:00'),
            'deletionDatetime' => new \DateTimeImmutable('2026-09-11 09:00:00'),
        ])->create();
        $messageId = (string) $message->id;

        $this->client->loginUser($member);
        $this->delete($space, $messageId);

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

        $tombstone = self::getContainer()->get(MessageRepository::class)->find($messageId);
        $this->assertInstanceOf(Message::class, $tombstone);
        $this->assertSame(
            '2026-09-11 09:00:00',
            $tombstone->deletionDatetime?->format('Y-m-d H:i:s'),
            'The first delete is the one that counts',
        );
    }

    public function test_a_malformed_message_id_is_a_404_rather_than_a_500(): void
    {
        // The id reaches the repository as a path segment. It is bound as a plain string there rather
        // than through the uuid type, so a value that is not a uuid at all is an ordinary miss.
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $this->channelOf($space);

        $this->client->loginUser($member);
        $this->delete($space, 'pas-un-uuid');

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

    public function test_a_space_without_a_channel_is_a_404(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();

        $this->client->loginUser($member);
        $this->delete($space, '3f2504e0-4f89-11d3-9a0c-0305e82c3301');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            // Not a message of its own: scoping the lookup to the space answers the anomaly and the
            // ordinary miss the same way, which is what the reaction endpoints already do.
            'detail' => 'Message introuvable',
            'description' => 'Message introuvable',
            'status' => 404,
            'type' => '/errors/404',
        ]);
    }

    public function test_a_space_pending_deletion_refuses_the_delete(): void
    {
        // Reading stays open for the whole grace period, writing does not, and taking a message down
        // is a write. This falls out of the processor using checkMemberForWrite().
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create([
            'deletionScheduledDatetime' => new \DateTimeImmutable('+30 days'),
        ]);
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $channel = $this->channelOf($space);

        $message = MessageFactory::new([
            'thread' => $channel,
            'author' => $member,
            'content' => 'on répète mardi',
            'creationDatetime' => new \DateTime('2026-09-10 20:00:00'),
        ])->create();
        $messageId = (string) $message->id;

        $this->client->loginUser($member);
        $this->delete($space, $messageId);

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

        $untouched = self::getContainer()->get(MessageRepository::class)->find($messageId);
        $this->assertInstanceOf(Message::class, $untouched);
        $this->assertNull($untouched->deletionDatetime);
    }

    public function test_the_collection_keeps_the_tombstone_in_place(): void
    {
        // The whole reason for a soft delete: the tombstone still sits between its neighbours, and the
        // total still counts it, so nothing above it shifts and no page boundary moves.
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $channel = $this->channelOf($space);

        $older = MessageFactory::new([
            'thread' => $channel,
            'author' => $member,
            'content' => 'on répète mardi',
            'creationDatetime' => new \DateTime('2026-09-10 20:00:00'),
        ])->create();
        $deleted = MessageFactory::new([
            'thread' => $channel,
            'author' => $member,
            'content' => '',
            'creationDatetime' => new \DateTime('2026-09-10 20:05:00'),
            'deletionDatetime' => new \DateTimeImmutable('2026-09-11 09:00:00'),
        ])->create();
        $newer = MessageFactory::new([
            'thread' => $channel,
            'author' => $member,
            'content' => 'finalement mercredi',
            'creationDatetime' => new \DateTime('2026-09-10 20:10:00'),
        ])->create();

        $this->client->loginUser($member);
        $this->client->request('GET', '/api/band_spaces/' . $space->id . '/chat/messages');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ChatMessage',
            '@id' => '/api/band_spaces/' . $space->id . '/chat/messages',
            '@type' => 'Collection',
            'totalItems' => 3,
            'member' => [
                $this->expectedMessage($newer->id, $space, $member, 'finalement mercredi', '2026-09-10T20:10:00+00:00', false),
                $this->expectedMessage($deleted->id, $space, $member, '', '2026-09-10T20:05:00+00:00', true),
                $this->expectedMessage($older->id, $space, $member, 'on répète mardi', '2026-09-10T20:00:00+00:00', false),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function expectedMessage(
        string $id,
        BandSpace $space,
        User $author,
        string $content,
        string $creationDatetime,
        bool $isDeleted,
    ): array {
        return [
            '@id' => '/api/chat_messages/id=' . $id . ';bandSpaceId=' . $space->id,
            '@type' => 'ChatMessage',
            'id' => $id,
            'band_space_id' => (string) $space->id,
            'author_id' => (string) $author->id,
            'author_username' => $author->username,
            'author_profile_picture_url' => null,
            'content' => $content,
            'creation_datetime' => $creationDatetime,
            'is_pinned' => false,
            'pinned_datetime' => null,
            'pinned_by_username' => null,
            'read_by_usernames' => [],
            'read_count' => 0,
            'image' => null,
            'voice_note' => null,
            'is_deleted' => $isDeleted,
            'update_datetime' => null,
            // Null on the tombstone, and that is the point: a deleted message offers its own author no
            // edit box, because the payload is what the pane asks before showing a pencil (#966).
            'editable_content' => $isDeleted ? null : $content,
            'reactions' => [],
            'attachments' => [],
        ];
    }

    private function channelOf(BandSpace $bandSpace): MessageThread
    {
        return MessageThreadFactory::new()->forBandSpace($bandSpace)->create();
    }

    private function delete(BandSpace $space, string $messageId): void
    {
        $this->client->jsonRequest(
            'DELETE',
            '/api/band_spaces/' . $space->id . '/chat/messages/' . $messageId,
            [],
            ['HTTP_ACCEPT' => 'application/ld+json'],
        );
    }
}
