<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Chat;

use App\Entity\BandSpace\BandSpace;
use App\Entity\Message\MessageThread;
use App\Entity\User;
use App\Enum\BandSpace\MembershipStatus;
use App\Enum\Notification\NotificationType;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Repository\Message\MessageMentionRepository;
use App\Repository\Message\MessageRepository;
use App\Repository\Notification\NotificationRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\Message\MessageFactory;
use App\Tests\Factory\Message\MessageMentionFactory;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * Editing your own chat message (#966).
 *
 * Every test makes exactly one authenticated request, because loginUser() authenticates exactly one
 * per method; anything that has to exist beforehand is seeded with factories.
 */
#[ResetDatabase]
class ChatMessagePatchTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const array HEADERS = [
        'CONTENT_TYPE' => 'application/merge-patch+json',
        'HTTP_ACCEPT' => 'application/ld+json',
    ];

    public function test_not_logged(): void
    {
        $space = BandSpaceFactory::new()->create();
        $channel = $this->channelOf($space);
        $message = MessageFactory::new(['thread' => $channel])->create(['content' => 'salut']);

        $this->patch($space, (string) $message->id, 'salut tout le monde');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    public function test_the_author_edits_their_own_message(): void
    {
        $space = BandSpaceFactory::new()->create();
        $author = $this->member($space, 'batteur');
        $channel = $this->channelOf($space);
        $message = MessageFactory::new([
            'thread' => $channel,
            'author' => $author,
            'content' => 'on répète mardi',
            'creationDatetime' => new \DateTime('2026-09-10 20:00:00'),
        ])->create();
        $messageId = (string) $message->id;

        $this->client->loginUser($author);
        $this->patch($space, $messageId, "on répète mercredi, j'apporte la basse");

        $this->assertResponseIsSuccessful();

        $edited = self::getContainer()->get(MessageRepository::class)->find($messageId);
        $this->assertNotNull($edited->updateDatetime, 'An edit has to be dated, it is what the marker reads');
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ChatMessage',
            '@id' => '/api/chat_messages/id=' . $messageId . ';bandSpaceId=' . $space->id,
            '@type' => 'ChatMessage',
            'id' => $messageId,
            'band_space_id' => (string) $space->id,
            'author_id' => (string) $author->id,
            'author_username' => 'batteur',
            'author_profile_picture_url' => null,
            // Sanitizer output, like every other read of a message: the apostrophe comes back escaped.
            'content' => 'on répète mercredi, j&#039;apporte la basse',
            'creation_datetime' => '2026-09-10T20:00:00+00:00',
            'update_datetime' => $edited->updateDatetime->format('c'),
            'editable_content' => "on répète mercredi, j'apporte la basse",
            'reactions' => [],
            'attachments' => [],
        ]);
    }

    public function test_another_member_cannot_edit_it(): void
    {
        $space = BandSpaceFactory::new()->create();
        $author = $this->member($space, 'batteur');
        $other = $this->member($space, 'bassiste');
        $channel = $this->channelOf($space);
        $message = MessageFactory::new(['thread' => $channel, 'author' => $author])->create(['content' => 'salut']);

        $this->client->loginUser($other);
        $this->patch($space, (string) $message->id, 'ce que je voulais dire');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Seul l\'auteur peut modifier ce message',
            'description' => 'Seul l\'auteur peut modifier ce message',
            'status' => 403,
            'type' => '/errors/403',
        ]);
    }

    public function test_a_non_member_cannot_edit_anything(): void
    {
        $space = BandSpaceFactory::new()->create();
        $author = $this->member($space, 'batteur');
        $stranger = UserFactory::new()->asBaseUser()->create(['username' => 'inconnu', 'email' => 'inconnu@test.com']);
        $channel = $this->channelOf($space);
        $message = MessageFactory::new(['thread' => $channel, 'author' => $author])->create(['content' => 'salut']);

        $this->client->loginUser($stranger);
        $this->patch($space, (string) $message->id, 'bonjour');

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
        $space = BandSpaceFactory::new()->create();
        $author = $this->member($space, 'batteur');
        $this->channelOf($space);

        $this->client->loginUser($author);
        $this->patch($space, '3f2504e0-4f89-11d3-9a0c-0305e82c3301', 'bonjour');

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

    public function test_a_message_of_another_band_space_is_a_404_and_not_a_403(): void
    {
        // The lookup is scoped to this space's channel, so a message id from elsewhere reads as
        // absent. Answering 403 would confirm it exists, which is a different thing to leak.
        $space = BandSpaceFactory::new()->create();
        $author = $this->member($space, 'batteur');
        $this->channelOf($space);

        $elsewhere = BandSpaceFactory::new()->create();
        $theirChannel = $this->channelOf($elsewhere);
        $theirMessage = MessageFactory::new(['thread' => $theirChannel, 'author' => $author])->create([
            'content' => 'chez les autres',
        ]);

        $this->client->loginUser($author);
        $this->patch($space, (string) $theirMessage->id, 'bonjour');

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

    public function test_a_space_pending_deletion_refuses_the_edit(): void
    {
        // Reading stays open for the whole grace period, writing does not, and an edit is a write.
        $space = BandSpaceFactory::new()->create(['deletionScheduledDatetime' => new \DateTimeImmutable('+30 days')]);
        $author = $this->member($space, 'batteur');
        $channel = $this->channelOf($space);
        $message = MessageFactory::new(['thread' => $channel, 'author' => $author])->create(['content' => 'salut']);

        $this->client->loginUser($author);
        $this->patch($space, (string) $message->id, 'salut tout le monde');

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

    public function test_a_blank_message_is_refused(): void
    {
        $space = BandSpaceFactory::new()->create();
        $author = $this->member($space, 'batteur');
        $channel = $this->channelOf($space);
        $message = MessageFactory::new(['thread' => $channel, 'author' => $author])->create(['content' => 'salut']);

        $this->client->loginUser($author);
        $this->patch($space, (string) $message->id, '');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'content',
                    'message' => 'Veuillez saisir un message',
                    'code' => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                ],
            ],
            'detail' => 'content: Veuillez saisir un message',
            'type' => '/validation_errors/c1051bb4-d103-4f74-8988-acbcafc7fdc3',
            'title' => 'An error occurred',
            'description' => 'content: Veuillez saisir un message',
        ]);
    }

    public function test_a_message_over_five_thousand_characters_is_refused(): void
    {
        $space = BandSpaceFactory::new()->create();
        $author = $this->member($space, 'batteur');
        $channel = $this->channelOf($space);
        $message = MessageFactory::new(['thread' => $channel, 'author' => $author])->create(['content' => 'salut']);

        $this->client->loginUser($author);
        $this->patch($space, (string) $message->id, str_repeat('a', 5001));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/d94b19cc-114f-4f44-9cc4-4138e80a87b9',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'content',
                    'message' => 'Le message ne peut pas dépasser 5000 caractères',
                    'code' => 'd94b19cc-114f-4f44-9cc4-4138e80a87b9',
                ],
            ],
            'detail' => 'content: Le message ne peut pas dépasser 5000 caractères',
            'type' => '/validation_errors/d94b19cc-114f-4f44-9cc4-4138e80a87b9',
            'title' => 'An error occurred',
            'description' => 'content: Le message ne peut pas dépasser 5000 caractères',
        ]);
    }

    public function test_an_edit_notifies_only_the_member_it_brings_in(): void
    {
        $space = BandSpaceFactory::new()->create(['name' => 'Les Trois Accords']);
        $author = $this->member($space, 'batteur');
        $alreadyNamed = $this->member($space, 'bassiste');
        $newlyNamed = $this->member($space, 'chanteuse');
        $channel = $this->channelOf($space);

        $message = MessageFactory::new(['thread' => $channel, 'author' => $author])->create([
            'content' => 'salut @[' . $alreadyNamed->id . ']',
        ]);
        MessageMentionFactory::new(['message' => $message, 'mentionedUser' => $alreadyNamed])->create();
        $messageId = (string) $message->id;

        $this->client->loginUser($author);
        $this->patch($space, $messageId, 'salut @[' . $alreadyNamed->id . '] et @[' . $newlyNamed->id . ']');

        $this->assertResponseIsSuccessful();

        $notifications = self::getContainer()->get(NotificationRepository::class);
        $forNewcomer = $notifications->findForRecipient($newlyNamed, 10, 0);
        $this->assertCount(1, $forNewcomer);
        $this->assertSame(NotificationType::BandSpaceChatMention, $forNewcomer[0]->type);
        $this->assertSame(
            [
                'band_space_id' => (string) $space->id,
                'band_space_name' => 'Les Trois Accords',
                'message_id' => $messageId,
                'actor_id' => (string) $author->id,
                'actor_username' => 'batteur',
            ],
            $forNewcomer[0]->payload,
        );
        $this->assertCount(
            0,
            $notifications->findForRecipient($alreadyNamed, 10, 0),
            'Somebody the message already named was told when it was written',
        );
        $this->assertCount(1, $notifications->findAll());

        // And the new name is a row, so the renderer can print it.
        $mentionedIds = $this->mentionedUserIdsOf($messageId);
        $this->assertSame(
            $this->sorted([(string) $alreadyNamed->id, (string) $newlyNamed->id]),
            $mentionedIds,
        );
    }

    public function test_keeping_a_mention_does_not_notify_again(): void
    {
        // The whole point of diffing against the previous content: fixing a typo must not re-ping
        // everybody the sentence already named.
        $space = BandSpaceFactory::new()->create();
        $author = $this->member($space, 'batteur');
        $named = $this->member($space, 'bassiste');
        $channel = $this->channelOf($space);

        $message = MessageFactory::new(['thread' => $channel, 'author' => $author])->create([
            'content' => 'salut @[' . $named->id . ']',
        ]);
        MessageMentionFactory::new(['message' => $message, 'mentionedUser' => $named])->create();

        $this->client->loginUser($author);
        $this->patch($space, (string) $message->id, 'salut @[' . $named->id . '] ça va ?');

        $this->assertResponseIsSuccessful();
        $this->assertCount(0, self::getContainer()->get(NotificationRepository::class)->findAll());
    }

    public function test_removing_a_mention_drops_the_row_that_named_them(): void
    {
        // The rows and the text move together: one left behind would print a name over a token the
        // author deleted.
        $space = BandSpaceFactory::new()->create();
        $author = $this->member($space, 'batteur');
        $named = $this->member($space, 'bassiste');
        $channel = $this->channelOf($space);

        $message = MessageFactory::new(['thread' => $channel, 'author' => $author])->create([
            'content' => 'salut @[' . $named->id . ']',
        ]);
        MessageMentionFactory::new(['message' => $message, 'mentionedUser' => $named])->create();
        $messageId = (string) $message->id;

        $this->client->loginUser($author);
        $this->patch($space, $messageId, 'salut tout le monde');

        $this->assertResponseIsSuccessful();
        $this->assertSame([], $this->mentionedUserIdsOf($messageId));
        $this->assertSame('salut tout le monde', $this->getResponseAsArray()['content']);
    }

    public function test_an_edit_keeps_naming_a_member_who_has_left_the_band(): void
    {
        // The reconciliation reads the tokens, not the resolved members: resolve() answers with active
        // members only, so dropping every row it does not name would turn a departed member's name
        // into `@inconnu` in history, which is the exact thing the mention rows exist to prevent.
        $space = BandSpaceFactory::new()->create();
        $author = $this->member($space, 'batteur');
        $gone = $this->member($space, 'bassiste');
        $channel = $this->channelOf($space);

        $message = MessageFactory::new(['thread' => $channel, 'author' => $author])->create([
            'content' => 'salut @[' . $gone->id . ']',
        ]);
        MessageMentionFactory::new(['message' => $message, 'mentionedUser' => $gone])->create();
        $messageId = (string) $message->id;

        $membership = self::getContainer()->get(BandSpaceMembershipRepository::class)
            ->findOneBy(['bandSpace' => $space->id, 'user' => $gone->id]);
        $membership->status = MembershipStatus::Left;
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        $this->client->loginUser($author);
        $this->patch($space, $messageId, 'salut @[' . $gone->id . '] ça va ?');

        $this->assertResponseIsSuccessful();
        $this->assertSame([(string) $gone->id], $this->mentionedUserIdsOf($messageId));
        $this->assertSame(
            'salut <span class="chat-mention">@bassiste</span> ça va ?',
            $this->getResponseAsArray()['content'],
        );
        $this->assertCount(
            0,
            self::getContainer()->get(NotificationRepository::class)->findAll(),
            'Somebody who has left the band is named but never notified',
        );
    }

    public function test_adding_tous_notifies_the_rest_of_the_band(): void
    {
        $space = BandSpaceFactory::new()->create(['name' => 'Les Trois Accords']);
        $author = $this->member($space, 'batteur');
        $bassist = $this->member($space, 'bassiste');
        $singer = $this->member($space, 'chanteuse');
        $channel = $this->channelOf($space);

        $message = MessageFactory::new(['thread' => $channel, 'author' => $author])->create([
            'content' => 'répète mardi',
        ]);
        $messageId = (string) $message->id;

        $this->client->loginUser($author);
        $this->patch($space, $messageId, '@[tous] répète mercredi');

        $this->assertResponseIsSuccessful();

        $notifications = self::getContainer()->get(NotificationRepository::class);
        $this->assertCount(1, $notifications->findForRecipient($bassist, 10, 0));
        $this->assertCount(1, $notifications->findForRecipient($singer, 10, 0));
        $this->assertCount(0, $notifications->findForRecipient($author, 10, 0), 'Writing @tous is not naming yourself');
        $this->assertCount(2, $notifications->findAll());

        // The author is recorded even though they are not told: the row is about who was named.
        $this->assertSame(
            $this->sorted([(string) $author->id, (string) $bassist->id, (string) $singer->id]),
            $this->mentionedUserIdsOf($messageId),
        );
        $this->assertSame(
            '<span class="chat-mention">@tous</span> répète mercredi',
            $this->getResponseAsArray()['content'],
        );
    }

    public function test_editing_a_tous_message_notifies_the_member_who_joined_since(): void
    {
        // Who to tell is read off the mention rows, not off resolving the old text again. Resolving
        // it would answer with today's roster, so this member would appear in both sets, cancel out,
        // and end up with a row nobody ever told them about: `@tous` still names them and the band
        // has grown since the message was written.
        $space = BandSpaceFactory::new()->create(['name' => 'Les Trois Accords']);
        $author = $this->member($space, 'batteur');
        $wasThere = $this->member($space, 'bassiste');
        $channel = $this->channelOf($space);

        $message = MessageFactory::new(['thread' => $channel, 'author' => $author])->create([
            'content' => '@[tous] répète mardi',
        ]);
        MessageMentionFactory::new(['message' => $message, 'mentionedUser' => $author])->create();
        MessageMentionFactory::new(['message' => $message, 'mentionedUser' => $wasThere])->create();
        $messageId = (string) $message->id;

        $joinedSince = $this->member($space, 'chanteuse');

        $this->client->loginUser($author);
        $this->patch($space, $messageId, '@[tous] répète mercredi');

        $this->assertResponseIsSuccessful();

        $notifications = self::getContainer()->get(NotificationRepository::class);
        $this->assertCount(1, $notifications->findForRecipient($joinedSince, 10, 0));
        $this->assertCount(
            0,
            $notifications->findForRecipient($wasThere, 10, 0),
            'Somebody the message already named was told when it was written',
        );
        $this->assertCount(1, $notifications->findAll());
        $this->assertSame(
            $this->sorted([(string) $author->id, (string) $wasThere->id, (string) $joinedSince->id]),
            $this->mentionedUserIdsOf($messageId),
        );
    }

    public function test_an_identical_body_is_not_an_edit(): void
    {
        // A client sending back what is already stored has changed nothing, and « modifié » is a
        // claim about the message rather than a detail of it.
        $space = BandSpaceFactory::new()->create();
        $author = $this->member($space, 'batteur');
        $channel = $this->channelOf($space);
        $message = MessageFactory::new([
            'thread' => $channel,
            'author' => $author,
            'content' => 'on répète mardi',
            'creationDatetime' => new \DateTime('2026-09-10 20:00:00'),
        ])->create();
        $messageId = (string) $message->id;

        $this->client->loginUser($author);
        $this->patch($space, $messageId, 'on répète mardi');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ChatMessage',
            '@id' => '/api/chat_messages/id=' . $messageId . ';bandSpaceId=' . $space->id,
            '@type' => 'ChatMessage',
            'id' => $messageId,
            'band_space_id' => (string) $space->id,
            'author_id' => (string) $author->id,
            'author_username' => 'batteur',
            'author_profile_picture_url' => null,
            'content' => 'on répète mardi',
            'creation_datetime' => '2026-09-10T20:00:00+00:00',
            'update_datetime' => null,
            'editable_content' => 'on répète mardi',
            'reactions' => [],
            'attachments' => [],
        ]);
        $this->assertNull(
            self::getContainer()->get(MessageRepository::class)->find($messageId)->updateDatetime,
            'Nothing changed, so nothing was stamped',
        );
    }

    /**
     * @return string[] sorted, so the assertion does not depend on insertion order
     */
    private function mentionedUserIdsOf(string $messageId): array
    {
        $mentions = self::getContainer()->get(MessageMentionRepository::class)->findAll();

        return $this->sorted(array_map(
            static fn ($mention): string => (string) $mention->mentionedUser->id,
            array_filter($mentions, static fn ($mention): bool => (string) $mention->message->id === $messageId),
        ));
    }

    /**
     * @param string[] $ids
     *
     * @return string[]
     */
    private function sorted(array $ids): array
    {
        $ids = array_values($ids);
        sort($ids);

        return $ids;
    }

    private function member(BandSpace $bandSpace, string $username): User
    {
        $user = UserFactory::new()->asBaseUser()->create([
            'username' => $username,
            'email' => $username . '@test.com',
        ]);
        BandSpaceMembershipFactory::new(['bandSpace' => $bandSpace, 'user' => $user])->create();

        return $user;
    }

    private function channelOf(BandSpace $bandSpace): MessageThread
    {
        return MessageThreadFactory::new()->forBandSpace($bandSpace)->create();
    }

    private function patch(BandSpace $bandSpace, string $messageId, string $content): void
    {
        $this->client->jsonRequest(
            'PATCH',
            '/api/band_spaces/' . $bandSpace->id . '/chat/messages/' . $messageId,
            ['content' => $content],
            self::HEADERS,
        );
    }
}
