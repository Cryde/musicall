<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Chat;

use App\Entity\BandSpace\BandSpace;
use App\Entity\Message\Message;
use App\Entity\User;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\Message\MessageFactory;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * A window of the chat anchored on one message (#1039), which is what jumping to a pinned message, a
 * mention or a link lands on, wherever it sits in the history.
 */
#[ResetDatabase]
class ChatMessageWindowTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_not_logged(): void
    {
        $space = BandSpaceFactory::new()->create();

        $this->window($space, 'around', '3f2504e0-4f89-41d3-9a0c-0305e82c3301');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    public function test_around_lands_on_the_message_with_twenty_five_on_each_side(): void
    {
        [$member, $space, $messages] = $this->conversationOf(60);
        $anchor = $messages[30];

        $this->client->loginUser($member);
        $this->window($space, 'around', (string) $anchor->id);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals($this->expectedWindow($space, $member, array_slice($messages, 5, 51), hasOlder: true, hasNewer: true, total: 60));
    }

    public function test_around_a_message_near_the_start_says_there_is_nothing_older(): void
    {
        [$member, $space, $messages] = $this->conversationOf(60);

        $this->client->loginUser($member);
        $this->window($space, 'around', (string) $messages[2]->id);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals($this->expectedWindow($space, $member, array_slice($messages, 0, 28), hasOlder: false, hasNewer: true, total: 60));
    }

    public function test_around_the_newest_message_reaches_the_live_end(): void
    {
        [$member, $space, $messages] = $this->conversationOf(60);

        $this->client->loginUser($member);
        $this->window($space, 'around', (string) $messages[59]->id);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals($this->expectedWindow($space, $member, array_slice($messages, 34, 26), hasOlder: true, hasNewer: false, total: 60));
    }

    public function test_before_reads_the_fifty_older_ones_without_the_anchor(): void
    {
        [$member, $space, $messages] = $this->conversationOf(60);

        $this->client->loginUser($member);
        $this->window($space, 'before', (string) $messages[55]->id);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals($this->expectedWindow($space, $member, array_slice($messages, 5, 50), hasOlder: true, hasNewer: true, total: 60));
    }

    public function test_after_reads_the_newer_ones_up_to_the_live_end(): void
    {
        [$member, $space, $messages] = $this->conversationOf(60);

        $this->client->loginUser($member);
        $this->window($space, 'after', (string) $messages[20]->id);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals($this->expectedWindow($space, $member, array_slice($messages, 21, 39), hasOlder: true, hasNewer: false, total: 60));
    }

    /**
     * Messages written in the same second, which the column cannot tell apart: the id breaks the tie
     * in the direction the list orders by, so reading on from one of them neither repeats nor skips.
     */
    public function test_messages_sharing_a_second_are_neither_repeated_nor_skipped(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $channel = MessageThreadFactory::new()->forBandSpace($space)->create();
        $sameSecond = [];
        foreach (range(1, 5) as $index) {
            $sameSecond[] = MessageFactory::new([
                'thread' => $channel,
                'author' => $member,
                'content' => 'message ' . $index,
                'creationDatetime' => new \DateTime('2026-09-10 20:00:00'),
            ])->create();
        }
        // The order the list itself uses for a tie.
        usort($sameSecond, static fn (Message $a, Message $b): int => strcmp((string) $a->id, (string) $b->id));

        $this->client->loginUser($member);
        $this->window($space, 'after', (string) $sameSecond[1]->id);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals($this->expectedWindow($space, $member, array_slice($sameSecond, 2), hasOlder: true, hasNewer: false, total: 5, datetime: '2026-09-10T20:00:00+00:00'));
    }

    /** A deleted message is a tombstone (#967), and a fine thing to land on: the link still means something. */
    public function test_a_deleted_message_can_be_landed_on(): void
    {
        [$member, $space, $messages] = $this->conversationOf(1);
        $tombstone = $messages[0];
        $tombstone->content = '';
        $tombstone->deletionDatetime = new \DateTimeImmutable('2026-09-11 08:00:00');
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        $this->client->loginUser($member);
        $this->window($space, 'around', (string) $tombstone->id);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ChatMessageWindow',
            '@id' => '/api/band_spaces/' . $space->id . '/chat/message_window',
            '@type' => 'ChatMessageWindow',
            'band_space_id' => (string) $space->id,
            'messages' => [
                [
                    '@id' => '/api/chat_messages/id=' . $tombstone->id . ';bandSpaceId=' . $space->id,
                    '@type' => 'ChatMessage',
                    'id' => (string) $tombstone->id,
                    'band_space_id' => (string) $space->id,
                    'author_id' => (string) $member->id,
                    'author_username' => 'batteur',
                    'author_profile_picture_url' => null,
                    'content' => '',
                    'creation_datetime' => '2026-09-10T10:00:00+00:00',
                    'reactions' => [],
                    'attachments' => [],
                    'update_datetime' => null,
                    'editable_content' => null,
                    'is_deleted' => true,
                    'is_pinned' => false,
                    'pinned_datetime' => null,
                    'pinned_by_username' => null,
                    'read_by_usernames' => [],
                    'read_count' => 0,
                    'image' => null,
                    'voice_note' => null,
                ],
            ],
            'has_older' => false,
            'has_newer' => false,
            'total_items' => 1,
        ]);
    }

    public function test_no_anchor_is_refused(): void
    {
        [$member, $space] = $this->conversationOf(1);

        $this->client->loginUser($member);
        $this->client->jsonRequest('GET', '/api/band_spaces/' . $space->id . '/chat/message_window', [], ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertBadRequest();
    }

    public function test_two_anchors_are_refused(): void
    {
        [$member, $space, $messages] = $this->conversationOf(2);

        $this->client->loginUser($member);
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $space->id . '/chat/message_window?around=' . $messages[0]->id . '&after=' . $messages[1]->id,
            [],
            ['HTTP_ACCEPT' => 'application/ld+json'],
        );

        $this->assertBadRequest();
    }

    public function test_an_anchor_that_is_not_a_uuid_is_refused_on_its_parameter(): void
    {
        [$member, $space] = $this->conversationOf(1);

        $this->client->loginUser($member);
        $this->window($space, 'around', 'pas-un-uuid');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/51120b12-a2bc-41bf-aa53-cd73daf330d0',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'around',
                    'message' => 'Cette valeur n\'est pas un UUID valide.',
                    'code' => '51120b12-a2bc-41bf-aa53-cd73daf330d0',
                ],
            ],
            'detail' => 'around: Cette valeur n\'est pas un UUID valide.',
            'type' => '/validation_errors/51120b12-a2bc-41bf-aa53-cd73daf330d0',
            'title' => 'An error occurred',
            'description' => 'around: Cette valeur n\'est pas un UUID valide.',
        ]);
    }

    /** A message of another band's chat cannot anchor a window here, nor tell whether it exists. */
    public function test_a_message_of_another_band_is_not_found(): void
    {
        [$member, $space] = $this->conversationOf(1);
        [, , $elsewhere] = $this->conversationOf(1, username: 'autre', email: 'autre@test.com');

        $this->client->loginUser($member);
        $this->window($space, 'around', (string) $elsewhere[0]->id);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/404',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Message introuvable',
            'status' => 404,
            'type' => '/errors/404',
            'description' => 'Message introuvable',
        ]);
    }

    public function test_a_non_member_cannot_read_a_window(): void
    {
        [, $space, $messages] = $this->conversationOf(1);
        $outsider = UserFactory::new()->asBaseUser()->create(['username' => 'intrus', 'email' => 'intrus@test.com']);

        $this->client->loginUser($outsider);
        $this->window($space, 'around', (string) $messages[0]->id);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/403',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Vous n\'êtes pas membre de ce Band Space',
            'status' => 403,
            'type' => '/errors/403',
            'description' => 'Vous n\'êtes pas membre de ce Band Space',
        ]);
    }

    private function assertBadRequest(): void
    {
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/400',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Indiquez un seul message de référence : around, before ou after',
            'status' => 400,
            'type' => '/errors/400',
            'description' => 'Indiquez un seul message de référence : around, before ou after',
        ]);
    }

    /**
     * A member and a conversation of `$count` messages one minute apart, oldest first.
     *
     * @return array{User, BandSpace, list<Message>}
     */
    private function conversationOf(int $count, string $username = 'batteur', string $email = 'batteur@test.com'): array
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => $username, 'email' => $email]);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $channel = MessageThreadFactory::new()->forBandSpace($space)->create();

        $messages = [];
        foreach (range(0, $count - 1) as $index) {
            $messages[] = MessageFactory::new([
                'thread' => $channel,
                'author' => $member,
                'content' => 'message ' . $index,
                'creationDatetime' => (new \DateTime('2026-09-10 10:00:00'))->modify('+' . $index . ' minutes'),
            ])->create();
        }

        return [$member, $space, $messages];
    }

    /**
     * @param list<Message> $messages oldest first
     *
     * @return array<string, mixed>
     */
    private function expectedWindow(BandSpace $space, User $author, array $messages, bool $hasOlder, bool $hasNewer, int $total, ?string $datetime = null): array
    {
        return [
            '@context' => '/api/contexts/ChatMessageWindow',
            '@id' => '/api/band_spaces/' . $space->id . '/chat/message_window',
            '@type' => 'ChatMessageWindow',
            'band_space_id' => (string) $space->id,
            'messages' => array_map(fn (Message $message): array => [
                '@id' => '/api/chat_messages/id=' . $message->id . ';bandSpaceId=' . $space->id,
                '@type' => 'ChatMessage',
                'id' => (string) $message->id,
                'band_space_id' => (string) $space->id,
                'author_id' => (string) $author->id,
                'author_username' => $author->username,
                'author_profile_picture_url' => null,
                'content' => $message->content,
                'creation_datetime' => $datetime ?? $message->creationDatetime->format('c'),
                'reactions' => [],
                'attachments' => [],
                'update_datetime' => null,
                'editable_content' => $message->content,
                'is_deleted' => false,
                'is_pinned' => false,
                'pinned_datetime' => null,
                'pinned_by_username' => null,
                'read_by_usernames' => [],
                'read_count' => 0,
                'image' => null,
                'voice_note' => null,
            ], $messages),
            'has_older' => $hasOlder,
            'has_newer' => $hasNewer,
            'total_items' => $total,
        ];
    }

    private function window(BandSpace $space, string $direction, string $messageId): void
    {
        $this->client->jsonRequest(
            'GET',
            '/api/band_spaces/' . $space->id . '/chat/message_window?' . $direction . '=' . $messageId,
            [],
            ['HTTP_ACCEPT' => 'application/ld+json'],
        );
    }
}
