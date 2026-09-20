<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Chat;

use App\Entity\BandSpace\BandSpace;
use App\Entity\Message\Message;
use App\Entity\Message\MessageThread;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceSearchResultType;
use App\Repository\BandSpace\Filter\TaskFilter;
use App\Repository\BandSpace\TaskRepository;
use App\Repository\Message\MessageAttachmentRepository;
use App\Service\BandSpace\ChatMessageTaskSeed;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\TaskFactory;
use App\Tests\Factory\Message\MessageAttachmentFactory;
use App\Tests\Factory\Message\MessageFactory;
use App\Tests\Factory\Message\MessageMentionFactory;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * Turning a chat message into a task (#979).
 */
#[ResetDatabase]
class ChatMessageTaskCreateTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_not_logged(): void
    {
        $space = BandSpaceFactory::new()->create();
        $message = $this->message($this->channelOf($space), UserFactory::new()->asBaseUser()->create());

        $this->client->request('POST', $this->url($space, $message));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    public function test_a_non_member_cannot_turn_a_message_into_a_task(): void
    {
        $stranger = UserFactory::new()->asBaseUser()->create(['username' => 'inconnu', 'email' => 'inconnu@test.com']);
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $message = $this->message($this->channelOf($space), $member);

        $this->client->loginUser($stranger);
        $this->client->request('POST', $this->url($space, $message));

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

    public function test_a_space_pending_deletion_refuses_it(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create(['deletionScheduledDatetime' => new \DateTimeImmutable('+30 days')]);
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $message = $this->message($this->channelOf($space), $member);

        $this->client->loginUser($member);
        $this->client->request('POST', $this->url($space, $message));

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
        $this->assertSame([], $this->tasksOf($space));
    }

    public function test_a_message_from_another_band_space_is_not_found(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $this->channelOf($space);

        $otherSpace = BandSpaceFactory::new()->create();
        $foreignMessage = $this->message($this->channelOf($otherSpace), $member, 'le secret des voisins');

        $this->client->loginUser($member);
        $this->client->request('POST', $this->url($space, $foreignMessage));

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
        $this->assertSame([], $this->tasksOf($space));
    }

    public function test_a_tombstone_cannot_become_a_task(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $message = MessageFactory::new([
            'thread' => $this->channelOf($space),
            'author' => $member,
            'content' => '',
            'creationDatetime' => new \DateTime('2026-09-10 20:00:00'),
            'deletionDatetime' => new \DateTimeImmutable('2026-09-10 20:05:00'),
        ])->create();

        $this->client->loginUser($member);
        $this->client->request('POST', $this->url($space, $message));

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
        $this->assertSame([], $this->tasksOf($space));
    }

    public function test_a_member_turns_someone_elses_message_into_a_task(): void
    {
        $author = UserFactory::new()->asBaseUser()->create(['username' => 'chanteuse', 'email' => 'chanteuse@test.com']);
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $author])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $message = $this->message($this->channelOf($space), $author, 'Faut penser à ramener le câble XLR');

        $this->client->loginUser($member);
        $this->client->request('POST', $this->url($space, $message));

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $tasks = $this->tasksOf($space);
        $this->assertCount(1, $tasks);
        $task = $tasks[0];

        $this->assertJsonEquals([
            '@context' => '/api/contexts/Task',
            '@id' => '/api/band_spaces/' . $space->id . '/tasks/' . $task->id,
            '@type' => 'Task',
            'id' => (string) $task->id,
            'band_space_id' => (string) $space->id,
            'title' => 'Faut penser à ramener le câble XLR',
            // The author is named because they are not the member who clicked.
            'description' => "Message de chanteuse dans la discussion du groupe :\n\nFaut penser à ramener le câble XLR",
            'status' => 'todo',
            'priority' => 'normal',
            'due_date' => null,
            'created_by_id' => (string) $member->id,
            'created_by_username' => 'batteur',
            'category_id' => null,
            'category_name' => null,
            'assignees' => [],
            'archive_datetime' => null,
            'completed_datetime' => null,
            'position' => 0,
            'creation_datetime' => $task->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => null,
            'comment_count' => 0,
            'file_count' => 0,
            'linked_message_id' => (string) $message->id,
        ]);

        // The reverse link is the attachment row #970 already knows how to render, label snapshotted.
        $stored = self::getContainer()->get(MessageAttachmentRepository::class)
            ->findByMessageIds([(string) $message->id])[(string) $message->id] ?? [];
        $this->assertSame(
            [['type' => BandSpaceSearchResultType::Task, 'targetId' => (string) $task->id, 'label' => 'Faut penser à ramener le câble XLR']],
            $stored,
        );
    }

    public function test_a_message_of_cards_only_is_named_by_its_first_card(): void
    {
        // A message can carry only cards since #971. Its cards are then all it says, so they name the
        // task, first card first, in the order the chat shows them: by kind, whatever order they were
        // attached in. The task card is written first here to prove it.
        $author = UserFactory::new()->asBaseUser()->create(['username' => 'chanteuse', 'email' => 'chanteuse@test.com']);
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $author])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $message = $this->message($this->channelOf($space), $author, '');
        MessageAttachmentFactory::new([
            'message' => $message,
            'targetType' => BandSpaceSearchResultType::Task,
            'label' => 'Réparer l\'ampli',
        ])->create();
        MessageAttachmentFactory::new([
            'message' => $message,
            'targetType' => BandSpaceSearchResultType::File,
            'label' => 'contrat.pdf',
        ])->create();

        $this->client->loginUser($member);
        $this->client->request('POST', $this->url($space, $message));

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $tasks = $this->tasksOf($space);
        $this->assertCount(1, $tasks);
        $task = $tasks[0];

        $this->assertJsonEquals([
            '@context' => '/api/contexts/Task',
            '@id' => '/api/band_spaces/' . $space->id . '/tasks/' . $task->id,
            '@type' => 'Task',
            'id' => (string) $task->id,
            'band_space_id' => (string) $space->id,
            'title' => 'contrat.pdf',
            'description' => "Message de chanteuse dans la discussion du groupe :\n\nPièces jointes :\n- contrat.pdf\n- Réparer l'ampli",
            'status' => 'todo',
            'priority' => 'normal',
            'due_date' => null,
            'created_by_id' => (string) $member->id,
            'created_by_username' => 'batteur',
            'category_id' => null,
            'category_name' => null,
            'assignees' => [],
            'archive_datetime' => null,
            'completed_datetime' => null,
            'position' => 0,
            'creation_datetime' => $task->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => null,
            'comment_count' => 0,
            'file_count' => 0,
            'linked_message_id' => (string) $message->id,
        ]);
    }

    public function test_a_long_message_is_cut_into_the_title_and_kept_whole_in_the_description(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();

        // 174 characters over 25 words of six, so the 120 character cut lands inside the eighteenth.
        $content = trim(str_repeat('azerty ', 25));
        $this->assertSame(174, mb_strlen($content));
        $message = $this->message($this->channelOf($space), $member, $content);

        $this->client->loginUser($member);
        $this->client->request('POST', $this->url($space, $message));

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $tasks = $this->tasksOf($space);
        $this->assertCount(1, $tasks);
        $task = $tasks[0];

        // Backed off to the last whole word rather than left mid-« azerty », and marked as an excerpt.
        $expectedTitle = trim(str_repeat('azerty ', 17)) . '…';
        $this->assertLessThanOrEqual(ChatMessageTaskSeed::MAX_TITLE_LENGTH, mb_strlen($expectedTitle));

        $this->assertJsonEquals([
            '@context' => '/api/contexts/Task',
            '@id' => '/api/band_spaces/' . $space->id . '/tasks/' . $task->id,
            '@type' => 'Task',
            'id' => (string) $task->id,
            'band_space_id' => (string) $space->id,
            'title' => $expectedTitle,
            'description' => "Message de batteur dans la discussion du groupe :\n\n" . $content,
            'status' => 'todo',
            'priority' => 'normal',
            'due_date' => null,
            'created_by_id' => (string) $member->id,
            'created_by_username' => 'batteur',
            'category_id' => null,
            'category_name' => null,
            'assignees' => [],
            'archive_datetime' => null,
            'completed_datetime' => null,
            'position' => 0,
            'creation_datetime' => $task->creationDatetime->format(\DateTimeInterface::ATOM),
            'update_datetime' => null,
            'comment_count' => 0,
            'file_count' => 0,
            'linked_message_id' => (string) $message->id,
        ]);
    }

    public function test_a_message_naming_somebody_reads_as_their_name(): void
    {
        $author = UserFactory::new()->asBaseUser()->create(['username' => 'chanteuse', 'email' => 'chanteuse@test.com']);
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $author])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $channel = $this->channelOf($space);

        $message = $this->message($channel, $author, '@[' . $member->id . '] ramène le câble');
        MessageMentionFactory::new(['message' => $message, 'mentionedUser' => $member])->create();

        $this->client->loginUser($member);
        $this->client->request('POST', $this->url($space, $message));

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $tasks = $this->tasksOf($space);
        $task = $tasks[0];
        $this->assertSame('@batteur ramène le câble', $task->title);
    }

    public function test_a_message_already_carrying_five_references_is_refused(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $message = $this->message($this->channelOf($space), $member, 'tout est là');

        foreach (range(1, 5) as $index) {
            $existing = TaskFactory::new()->create(['bandSpace' => $space, 'title' => 'Tâche ' . $index]);
            MessageAttachmentFactory::new([
                'message' => $message,
                'targetType' => BandSpaceSearchResultType::Task,
                'targetId' => (string) $existing->id,
                'label' => 'Tâche ' . $index,
            ])->create();
        }

        $this->client->loginUser($member);
        $this->client->request('POST', $this->url($space, $message));

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/409',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Ce message référence déjà 5 éléments, il ne peut pas en porter davantage.',
            'description' => 'Ce message référence déjà 5 éléments, il ne peut pas en porter davantage.',
            'status' => 409,
            'type' => '/errors/409',
        ]);
        // The five seeded ones, and nothing created by the refused call.
        $this->assertCount(5, $this->tasksOf($space));
    }

    public function test_a_task_carries_the_message_it_came_from(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $channel = $this->channelOf($space);

        $task = TaskFactory::new()->create([
            'bandSpace' => $space,
            'title' => 'Ramener le câble XLR',
            'createdBy' => $member,
            'creationDatetime' => new \DateTime('2026-09-10 20:01:00'),
        ]);
        $source = $this->message($channel, $member, 'Faut penser à ramener le câble XLR');
        // A later message pointing at the same task: the drawer must still name the first one.
        $later = MessageFactory::new([
            'thread' => $channel,
            'author' => $member,
            'content' => 'et cette tâche alors',
            'creationDatetime' => new \DateTime('2026-09-12 09:00:00'),
        ])->create();
        foreach ([$source, $later] as $linked) {
            MessageAttachmentFactory::new([
                'message' => $linked,
                'targetType' => BandSpaceSearchResultType::Task,
                'targetId' => (string) $task->id,
                'label' => 'Ramener le câble XLR',
            ])->create();
        }

        $this->client->loginUser($member);
        $this->client->request('GET', '/api/band_spaces/' . $space->id . '/tasks/' . $task->id);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Task',
            '@id' => '/api/band_spaces/' . $space->id . '/tasks/' . $task->id,
            '@type' => 'Task',
            'id' => (string) $task->id,
            'band_space_id' => (string) $space->id,
            'title' => 'Ramener le câble XLR',
            'description' => null,
            'status' => 'todo',
            'priority' => 'normal',
            'due_date' => null,
            'created_by_id' => (string) $member->id,
            'created_by_username' => 'batteur',
            'category_id' => null,
            'category_name' => null,
            'assignees' => [],
            'archive_datetime' => null,
            'completed_datetime' => null,
            'position' => 0,
            'creation_datetime' => '2026-09-10T20:01:00+00:00',
            'update_datetime' => null,
            'comment_count' => 0,
            'file_count' => 0,
            'linked_message_id' => (string) $source->id,
        ]);
    }

    /**
     * @return \App\Entity\BandSpace\Task[]
     */
    private function tasksOf(BandSpace $bandSpace): array
    {
        return self::getContainer()->get(TaskRepository::class)->findByBandSpace($bandSpace, new TaskFilter());
    }

    private function channelOf(BandSpace $bandSpace): MessageThread
    {
        return MessageThreadFactory::new()->forBandSpace($bandSpace)->create();
    }

    private function message(MessageThread $channel, User $author, string $content = 'une info'): Message
    {
        return MessageFactory::new([
            'thread' => $channel,
            'author' => $author,
            'content' => $content,
            // Pinned rather than left to faker: the response body is asserted whole.
            'creationDatetime' => new \DateTime('2026-09-10 20:00:00'),
        ])->create();
    }

    private function url(BandSpace $bandSpace, Message $message): string
    {
        return '/api/band_spaces/' . $bandSpace->id . '/chat/messages/' . $message->id . '/task';
    }
}
