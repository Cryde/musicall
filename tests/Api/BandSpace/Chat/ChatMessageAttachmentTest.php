<?php

declare(strict_types=1);

namespace App\Tests\Api\BandSpace\Chat;

use App\Entity\BandSpace\BandSpace;
use App\Entity\Message\MessageThread;
use App\Enum\BandSpace\BandSpaceSearchResultType;
use App\Enum\BandSpace\FinanceEntryScope;
use App\Repository\Message\MessageAttachmentRepository;
use App\Repository\Message\MessageRepository;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\BandSpace\AgendaEntryFactory;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\BandSpace\BandSpaceNoteFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileFactory;
use App\Tests\Factory\BandSpace\FinanceCategoryFactory;
use App\Tests\Factory\BandSpace\FinanceEntryFactory;
use App\Tests\Factory\BandSpace\SetlistFactory;
use App\Tests\Factory\BandSpace\SongFactory;
use App\Tests\Factory\BandSpace\TaskFactory;
use App\Tests\Factory\Message\MessageAttachmentFactory;
use App\Tests\Factory\Message\MessageFactory;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\User\UserFactory;
use App\Validator\Message\ValidChatAttachmentsValidator;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * A chat message carrying references to Band Space objects (#970).
 */
#[ResetDatabase]
class ChatMessageAttachmentTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    private const string OTHER_SPACE_TASK_ID = 'c0ffee00-0000-4000-8000-000000000001';

    public function test_not_logged(): void
    {
        $space = BandSpaceFactory::new()->create();
        $task = TaskFactory::new()->create(['bandSpace' => $space, 'title' => 'Réparer l\'ampli']);

        $this->post($space, 'regarde ça', ['task-' . $task->id]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals(['code' => 401, 'message' => 'JWT Token not found']);
    }

    public function test_a_non_member_cannot_reference_anything(): void
    {
        // The membership check belongs to the processor, so the validator says nothing when the sender
        // is not a member: otherwise a stranger would learn from a 422 whether an id exists in a space
        // they cannot read.
        $stranger = UserFactory::new()->asBaseUser()->create(['username' => 'inconnu', 'email' => 'inconnu@test.com']);
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $this->channelOf($space);
        $task = TaskFactory::new()->create(['bandSpace' => $space, 'title' => 'Réparer l\'ampli']);

        $this->client->loginUser($stranger);
        $this->post($space, 'regarde ça', ['task-' . $task->id]);

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

    public function test_a_space_pending_deletion_refuses_the_message(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create(['deletionScheduledDatetime' => new \DateTimeImmutable('+30 days')]);
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $this->channelOf($space);
        $task = TaskFactory::new()->create(['bandSpace' => $space, 'title' => 'Réparer l\'ampli']);

        $this->client->loginUser($member);
        $this->post($space, 'regarde ça', ['task-' . $task->id]);

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

    public function test_a_message_without_attachments_carries_an_empty_list(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $channel = $this->channelOf($space);

        $this->client->loginUser($member);
        $this->post($space, 'bonjour', []);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $message = self::getContainer()->get(MessageRepository::class)->findOneBy(['thread' => $channel->id]);
        $this->assertNotNull($message);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ChatMessage',
            '@id' => '/api/chat_messages/id=' . $message->id . ';bandSpaceId=' . $space->id,
            '@type' => 'ChatMessage',
            'id' => (string) $message->id,
            'band_space_id' => (string) $space->id,
            'author_id' => (string) $member->id,
            'author_username' => 'batteur',
            'author_profile_picture_url' => null,
            'content' => 'bonjour',
            'creation_datetime' => $message->creationDatetime->format('c'),
            'update_datetime' => null,
            'editable_content' => 'bonjour',
            'reactions' => [],
            'attachments' => [],
        ]);
    }

    public function test_posting_a_message_with_attachments_resolves_and_snapshots_them(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $channel = $this->channelOf($space);

        $agenda = AgendaEntryFactory::new()->create(['bandSpace' => $space, 'title' => 'Concert au Botanique']);
        $task = TaskFactory::new()->create(['bandSpace' => $space, 'title' => 'Réparer l\'ampli']);
        $note = BandSpaceNoteFactory::new()->create(['bandSpace' => $space, 'title' => 'Idées de reprises']);
        $file = BandSpaceFileFactory::new()->create(['bandSpace' => $space, 'originalName' => 'contrat.pdf']);
        $category = FinanceCategoryFactory::new()->create(['bandSpace' => $space]);
        $finance = FinanceEntryFactory::new()->create(['category' => $category, 'label' => 'Location du local']);

        $this->client->loginUser($member);
        // The cap, exactly: five is what a message may carry.
        $this->post($space, 'tout est là', [
            'agenda-' . $agenda->id,
            'task-' . $task->id,
            'note-' . $note->id,
            'file-' . $file->id,
            'finance-' . $finance->id,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $message = self::getContainer()->get(MessageRepository::class)->findOneBy(['thread' => $channel->id]);
        $this->assertNotNull($message);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ChatMessage',
            '@id' => '/api/chat_messages/id=' . $message->id . ';bandSpaceId=' . $space->id,
            '@type' => 'ChatMessage',
            'id' => (string) $message->id,
            'band_space_id' => (string) $space->id,
            'author_id' => (string) $member->id,
            'author_username' => 'batteur',
            'author_profile_picture_url' => null,
            'content' => 'tout est là',
            'creation_datetime' => $message->creationDatetime->format('c'),
            'update_datetime' => null,
            'editable_content' => 'tout est là',
            'reactions' => [],
            // Ordered by kind then target id, which is what makes the payload predictable: the rows
            // are written in one flush, so their second-granular datetime cannot separate them.
            'attachments' => [
                $this->expectedCard('agenda', (string) $agenda->id, 'Concert au Botanique'),
                $this->expectedCard('file', (string) $file->id, 'contrat.pdf'),
                $this->expectedCard('finance', (string) $finance->id, 'Location du local'),
                $this->expectedCard('note', (string) $note->id, 'Idées de reprises'),
                $this->expectedCard('task', (string) $task->id, 'Réparer l\'ampli'),
            ],
        ]);

        // The label really is snapshotted on the row, which is what the message falls back on once the
        // target is gone.
        $stored = self::getContainer()->get(MessageAttachmentRepository::class)
            ->findByMessageIds([(string) $message->id])[(string) $message->id] ?? [];
        $this->assertSame(
            ['Concert au Botanique', 'contrat.pdf', 'Location du local', 'Idées de reprises', 'Réparer l\'ampli'],
            array_column($stored, 'label'),
        );
    }

    public function test_every_one_of_the_seven_kinds_of_target_resolves(): void
    {
        // Seeded rather than posted, because a message may only be written with five: the point here
        // is that each of the seven kinds has a working lookup, the finance entry included, which is
        // the one reaching its space through another table.
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $channel = $this->channelOf($space);

        $agenda = AgendaEntryFactory::new()->create(['bandSpace' => $space, 'title' => 'Concert au Botanique']);
        $task = TaskFactory::new()->create(['bandSpace' => $space, 'title' => 'Réparer l\'ampli']);
        $note = BandSpaceNoteFactory::new()->create(['bandSpace' => $space, 'title' => 'Idées de reprises']);
        $file = BandSpaceFileFactory::new()->create(['bandSpace' => $space, 'originalName' => 'contrat.pdf']);
        $setlist = SetlistFactory::new()->create(['bandSpace' => $space, 'name' => 'Set du 14 juillet']);
        $song = SongFactory::new()->create(['bandSpace' => $space, 'title' => 'Ma dernière chanson']);
        $category = FinanceCategoryFactory::new()->create(['bandSpace' => $space]);
        $finance = FinanceEntryFactory::new()->create(['category' => $category, 'label' => 'Location du local']);

        $message = MessageFactory::new([
            'thread' => $channel,
            'author' => $member,
            'content' => 'tout est là',
            'creationDatetime' => new \DateTime('2026-09-10 20:00:00'),
        ])->create();

        $seeded = [
            [BandSpaceSearchResultType::Agenda, (string) $agenda->id, 'Concert au Botanique'],
            [BandSpaceSearchResultType::Task, (string) $task->id, 'Réparer l\'ampli'],
            [BandSpaceSearchResultType::Note, (string) $note->id, 'Idées de reprises'],
            [BandSpaceSearchResultType::File, (string) $file->id, 'contrat.pdf'],
            [BandSpaceSearchResultType::Setlist, (string) $setlist->id, 'Set du 14 juillet'],
            [BandSpaceSearchResultType::Song, (string) $song->id, 'Ma dernière chanson'],
            [BandSpaceSearchResultType::Finance, (string) $finance->id, 'Location du local'],
        ];
        foreach ($seeded as [$type, $targetId, $label]) {
            MessageAttachmentFactory::new([
                'message' => $message,
                'targetType' => $type,
                'targetId' => $targetId,
                'label' => $label,
            ])->create();
        }

        $this->client->loginUser($member);
        $this->client->request('GET', '/api/band_spaces/' . $space->id . '/chat/messages');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ChatMessage',
            '@id' => '/api/band_spaces/' . $space->id . '/chat/messages',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                $this->expectedMessage($message, $space, $member, 'tout est là', '2026-09-10T20:00:00+00:00', [
                    $this->expectedCard('agenda', (string) $agenda->id, 'Concert au Botanique'),
                    $this->expectedCard('file', (string) $file->id, 'contrat.pdf'),
                    $this->expectedCard('finance', (string) $finance->id, 'Location du local'),
                    $this->expectedCard('note', (string) $note->id, 'Idées de reprises'),
                    $this->expectedCard('setlist', (string) $setlist->id, 'Set du 14 juillet'),
                    $this->expectedCard('song', (string) $song->id, 'Ma dernière chanson'),
                    $this->expectedCard('task', (string) $task->id, 'Réparer l\'ampli'),
                ],
                    'tout est là'),
            ],
        ]);
    }

    public function test_a_malformed_identifier_is_refused(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $this->channelOf($space);

        $this->client->loginUser($member);
        // Not a uuid, which must be refused here rather than reaching Doctrine's uuid conversion and
        // coming back as a 500.
        $this->post($space, 'regarde ça', ['task-pas-un-uuid']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . ValidChatAttachmentsValidator::ERROR_CODE,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'attachments[0]',
                    'message' => 'Cet élément est introuvable dans ce Band Space',
                    'code' => ValidChatAttachmentsValidator::ERROR_CODE,
                ],
            ],
            'detail' => 'attachments[0]: Cet élément est introuvable dans ce Band Space',
            'type' => '/validation_errors/' . ValidChatAttachmentsValidator::ERROR_CODE,
            'title' => 'An error occurred',
            'description' => 'attachments[0]: Cet élément est introuvable dans ce Band Space',
        ]);
    }

    public function test_an_entry_that_is_not_a_string_is_refused(): void
    {
        // `attachments` is a plain array, so nothing stops a client putting a number or an object in
        // it. Refused like any other unusable identifier rather than reaching the parser as a type
        // error.
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $this->channelOf($space);

        $this->client->loginUser($member);
        $this->post($space, 'regarde ça', [42]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . ValidChatAttachmentsValidator::ERROR_CODE,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'attachments[0]',
                    'message' => 'Cet élément est introuvable dans ce Band Space',
                    'code' => ValidChatAttachmentsValidator::ERROR_CODE,
                ],
            ],
            'detail' => 'attachments[0]: Cet élément est introuvable dans ce Band Space',
            'type' => '/validation_errors/' . ValidChatAttachmentsValidator::ERROR_CODE,
            'title' => 'An error occurred',
            'description' => 'attachments[0]: Cet élément est introuvable dans ce Band Space',
        ]);
    }

    public function test_an_identifier_that_names_nothing_is_refused(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $this->channelOf($space);
        $task = TaskFactory::new()->create(['bandSpace' => $space, 'title' => 'Réparer l\'ampli']);

        $this->client->loginUser($member);
        $this->post($space, 'regarde ça', ['task-' . $task->id, 'note-' . self::OTHER_SPACE_TASK_ID]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . ValidChatAttachmentsValidator::ERROR_CODE,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'attachments[1]',
                    'message' => 'Cet élément est introuvable dans ce Band Space',
                    'code' => ValidChatAttachmentsValidator::ERROR_CODE,
                ],
            ],
            'detail' => 'attachments[1]: Cet élément est introuvable dans ce Band Space',
            'type' => '/validation_errors/' . ValidChatAttachmentsValidator::ERROR_CODE,
            'title' => 'An error occurred',
            'description' => 'attachments[1]: Cet élément est introuvable dans ce Band Space',
        ]);
    }

    public function test_a_target_belonging_to_another_band_space_is_refused(): void
    {
        // The one that matters most: #972 will let a member paste a URL, and a URL into a space they
        // are not a member of must not resolve.
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $this->channelOf($space);

        $otherSpace = BandSpaceFactory::new()->create();
        $otherTask = TaskFactory::new()->create(['bandSpace' => $otherSpace, 'title' => 'Secret des voisins']);

        $this->client->loginUser($member);
        $this->post($space, 'regarde ça', ['task-' . $otherTask->id]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . ValidChatAttachmentsValidator::ERROR_CODE,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'attachments[0]',
                    'message' => 'Cet élément est introuvable dans ce Band Space',
                    'code' => ValidChatAttachmentsValidator::ERROR_CODE,
                ],
            ],
            'detail' => 'attachments[0]: Cet élément est introuvable dans ce Band Space',
            'type' => '/validation_errors/' . ValidChatAttachmentsValidator::ERROR_CODE,
            'title' => 'An error occurred',
            'description' => 'attachments[0]: Cet élément est introuvable dans ce Band Space',
        ]);
    }

    public function test_another_members_personal_finance_entry_cannot_be_referenced(): void
    {
        // The one visibility rule a band space has today, the same one the command palette applies:
        // a personal entry belongs to the member it names.
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $other = UserFactory::new()->asBaseUser()->create(['username' => 'bassiste', 'email' => 'bassiste@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $otherMembership = BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $other])->create();
        $this->channelOf($space);

        $category = FinanceCategoryFactory::new()->create(['bandSpace' => $space]);
        $personal = FinanceEntryFactory::new()->create([
            'category' => $category,
            'label' => 'Cordes de la bassiste',
            'scope' => FinanceEntryScope::Personal,
            'member' => $otherMembership,
        ]);

        $this->client->loginUser($member);
        $this->post($space, 'regarde ça', ['finance-' . $personal->id]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . ValidChatAttachmentsValidator::ERROR_CODE,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'attachments[0]',
                    'message' => 'Cet élément est introuvable dans ce Band Space',
                    'code' => ValidChatAttachmentsValidator::ERROR_CODE,
                ],
            ],
            'detail' => 'attachments[0]: Cet élément est introuvable dans ce Band Space',
            'type' => '/validation_errors/' . ValidChatAttachmentsValidator::ERROR_CODE,
            'title' => 'An error occurred',
            'description' => 'attachments[0]: Cet élément est introuvable dans ce Band Space',
        ]);
    }

    public function test_the_same_target_twice_is_refused(): void
    {
        // Refused rather than silently de-duplicated: dropping half of what was asked for is how a
        // picker starts looking broken, and the unique index would answer a duplicate with a 500.
        // Compared on the resolved target, so two spellings of one uuid are caught too.
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $this->channelOf($space);
        $task = TaskFactory::new()->create(['bandSpace' => $space, 'title' => 'Réparer l\'ampli']);

        $this->client->loginUser($member);
        $this->post($space, 'regarde ça', ['task-' . $task->id, 'task-' . mb_strtoupper((string) $task->id)]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/' . ValidChatAttachmentsValidator::DUPLICATE_ERROR_CODE,
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'attachments[1]',
                    'message' => 'Cet élément est déjà référencé dans ce message',
                    'code' => ValidChatAttachmentsValidator::DUPLICATE_ERROR_CODE,
                ],
            ],
            'detail' => 'attachments[1]: Cet élément est déjà référencé dans ce message',
            'type' => '/validation_errors/' . ValidChatAttachmentsValidator::DUPLICATE_ERROR_CODE,
            'title' => 'An error occurred',
            'description' => 'attachments[1]: Cet élément est déjà référencé dans ce message',
        ]);
    }

    public function test_more_than_five_attachments_are_refused(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $this->channelOf($space);

        $identifiers = [];
        foreach (range(1, 6) as $index) {
            $task = TaskFactory::new()->create(['bandSpace' => $space, 'title' => 'Tâche ' . $index]);
            $identifiers[] = 'task-' . $task->id;
        }

        $this->client->loginUser($member);
        $this->post($space, 'regarde ça', $identifiers);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        // The cap alone, with no violation per entry on top of it: the constraints are Sequentially.
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ConstraintViolation',
            '@id' => '/api/validation_errors/756b1212-697c-468d-a9ad-50dd783bb169',
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'violations' => [
                [
                    'propertyPath' => 'attachments',
                    'message' => 'Un message ne peut pas référencer plus de 5 éléments',
                    'code' => '756b1212-697c-468d-a9ad-50dd783bb169',
                ],
            ],
            'detail' => 'attachments: Un message ne peut pas référencer plus de 5 éléments',
            'type' => '/validation_errors/756b1212-697c-468d-a9ad-50dd783bb169',
            'title' => 'An error occurred',
            'description' => 'attachments: Un message ne peut pas référencer plus de 5 éléments',
        ]);
    }

    public function test_the_collection_resolves_a_page_of_mixed_attachments(): void
    {
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $channel = $this->channelOf($space);

        $task = TaskFactory::new()->create(['bandSpace' => $space, 'title' => 'Réparer l\'ampli']);
        $song = SongFactory::new()->create(['bandSpace' => $space, 'title' => 'Ma dernière chanson']);
        $setlist = SetlistFactory::new()->create(['bandSpace' => $space, 'name' => 'Set du 14 juillet']);

        $older = MessageFactory::new([
            'thread' => $channel,
            'author' => $member,
            'content' => 'la tâche et le morceau',
            'creationDatetime' => new \DateTime('2026-09-10 20:00:00'),
        ])->create();
        MessageAttachmentFactory::new(['message' => $older, 'targetType' => BandSpaceSearchResultType::Task, 'targetId' => $task->id, 'label' => 'Réparer l\'ampli'])->create();
        MessageAttachmentFactory::new(['message' => $older, 'targetType' => BandSpaceSearchResultType::Song, 'targetId' => $song->id, 'label' => 'Ma dernière chanson'])->create();

        $newer = MessageFactory::new([
            'thread' => $channel,
            'author' => $member,
            'content' => 'et la setlist',
            'creationDatetime' => new \DateTime('2026-09-10 20:05:00'),
        ])->create();
        MessageAttachmentFactory::new(['message' => $newer, 'targetType' => BandSpaceSearchResultType::Setlist, 'targetId' => $setlist->id, 'label' => 'Set du 14 juillet'])->create();

        $bare = MessageFactory::new([
            'thread' => $channel,
            'author' => $member,
            'content' => 'rien ici',
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
                $this->expectedMessage($bare, $space, $member, 'rien ici', '2026-09-10T20:10:00+00:00', [],
                    'rien ici'),
                $this->expectedMessage($newer, $space, $member, 'et la setlist', '2026-09-10T20:05:00+00:00', [
                    $this->expectedCard('setlist', (string) $setlist->id, 'Set du 14 juillet'),
                ],
                    'et la setlist'),
                $this->expectedMessage($older, $space, $member, 'la tâche et le morceau', '2026-09-10T20:00:00+00:00', [
                    $this->expectedCard('song', (string) $song->id, 'Ma dernière chanson'),
                    $this->expectedCard('task', (string) $task->id, 'Réparer l\'ampli'),
                ],
                    'la tâche et le morceau'),
            ],
        ]);
    }

    public function test_a_deleted_target_keeps_the_label_it_was_attached_with(): void
    {
        // The whole reason the label is snapshotted, and the reason this table needs no delete guard:
        // the task is gone and the message still says what it pointed at.
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
        MessageAttachmentFactory::new([
            'message' => $message,
            'targetType' => BandSpaceSearchResultType::Task,
            'targetId' => self::OTHER_SPACE_TASK_ID,
            'label' => 'Réparer l\'ampli',
        ])->create();

        $this->client->loginUser($member);
        $this->client->request('GET', '/api/band_spaces/' . $space->id . '/chat/messages');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ChatMessage',
            '@id' => '/api/band_spaces/' . $space->id . '/chat/messages',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                $this->expectedMessage($message, $space, $member, 'regarde ça', '2026-09-10T20:00:00+00:00', [
                    [
                        'type' => 'task',
                        'target_id' => self::OTHER_SPACE_TASK_ID,
                        'label' => 'Réparer l\'ampli',
                        'is_available' => false,
                    ],
                ],
                    'regarde ça'),
            ],
        ]);
    }

    public function test_a_renamed_target_keeps_the_label_it_was_attached_with(): void
    {
        // The card is named by the snapshot, never by the title read back now. A rename therefore
        // does not show through, which is the price of every reader seeing the same card and of no
        // reader being shown a value its owner disclosed only once. The link still opens the target.
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $channel = $this->channelOf($space);
        $task = TaskFactory::new()->create(['bandSpace' => $space, 'title' => 'Réparer l\'ampli du local']);

        $message = MessageFactory::new([
            'thread' => $channel,
            'author' => $member,
            'content' => 'regarde ça',
            'creationDatetime' => new \DateTime('2026-09-10 20:00:00'),
        ])->create();
        MessageAttachmentFactory::new([
            'message' => $message,
            'targetType' => BandSpaceSearchResultType::Task,
            'targetId' => $task->id,
            'label' => 'Réparer l\'ampli',
        ])->create();

        $this->client->loginUser($member);
        $this->client->request('GET', '/api/band_spaces/' . $space->id . '/chat/messages');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ChatMessage',
            '@id' => '/api/band_spaces/' . $space->id . '/chat/messages',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                $this->expectedMessage($message, $space, $member, 'regarde ça', '2026-09-10T20:00:00+00:00', [
                    $this->expectedCard('task', (string) $task->id, 'Réparer l\'ampli'),
                ],
                    'regarde ça'),
            ],
        ]);
    }

    public function test_a_personal_finance_entry_renamed_after_the_fact_does_not_leak_its_new_label(): void
    {
        // A personal entry is walled to the member it names everywhere else in the band space, so
        // attaching it discloses its label once, at that moment. Reading the card back must not hand
        // the rest of the band whatever it has been renamed to since.
        $owner = UserFactory::new()->asBaseUser()->create(['username' => 'bassiste', 'email' => 'bassiste@test.com']);
        $reader = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        $ownerMembership = BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $owner])->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $reader])->create();
        $channel = $this->channelOf($space);

        $category = FinanceCategoryFactory::new()->create(['bandSpace' => $space]);
        $personal = FinanceEntryFactory::new()->create([
            'category' => $category,
            // What it has been renamed to since it was attached.
            'label' => 'Facture du psychologue',
            'scope' => FinanceEntryScope::Personal,
            'member' => $ownerMembership,
        ]);

        $message = MessageFactory::new([
            'thread' => $channel,
            'author' => $owner,
            'content' => 'ma dépense',
            'creationDatetime' => new \DateTime('2026-09-10 20:00:00'),
        ])->create();
        MessageAttachmentFactory::new([
            'message' => $message,
            'targetType' => BandSpaceSearchResultType::Finance,
            'targetId' => $personal->id,
            'label' => 'Cordes',
        ])->create();

        $this->client->loginUser($reader);
        $this->client->request('GET', '/api/band_spaces/' . $space->id . '/chat/messages');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ChatMessage',
            '@id' => '/api/band_spaces/' . $space->id . '/chat/messages',
            '@type' => 'Collection',
            'totalItems' => 1,
            'member' => [
                $this->expectedMessage($message, $space, $owner, 'ma dépense', '2026-09-10T20:00:00+00:00', [
                    // The snapshot, and still available: the entry is there, it is simply not renamed
                    // under the reader's nose.
                    $this->expectedCard('finance', (string) $personal->id, 'Cordes'),
                ],
                    null),
            ],
        ]);
    }

    public function test_resolving_a_page_costs_one_query_per_kind_of_target(): void
    {
        // The constraint the whole feature turns on. Ten messages carrying fifty attachments of two
        // kinds must cost the page's own queries plus one for the rows and one per kind: resolving per
        // message, or per attachment, is what would make a chat scroll expensive (#730).
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $channel = $this->channelOf($space);

        $tasks = [];
        foreach (range(1, 3) as $index) {
            $tasks[] = TaskFactory::new()->create(['bandSpace' => $space, 'title' => 'Tâche ' . $index]);
        }
        $notes = [];
        foreach (range(1, 2) as $index) {
            $notes[] = BandSpaceNoteFactory::new()->create(['bandSpace' => $space, 'title' => 'Note ' . $index]);
        }

        foreach (range(1, 10) as $index) {
            $message = MessageFactory::new([
                'thread' => $channel,
                'author' => $member,
                'content' => 'message ' . $index,
                'creationDatetime' => new \DateTime(sprintf('2026-09-10 20:%02d:00', $index)),
            ])->create();

            foreach ($tasks as $task) {
                MessageAttachmentFactory::new(['message' => $message, 'targetType' => BandSpaceSearchResultType::Task, 'targetId' => $task->id, 'label' => $task->title])->create();
            }
            foreach ($notes as $note) {
                MessageAttachmentFactory::new(['message' => $message, 'targetType' => BandSpaceSearchResultType::Note, 'targetId' => $note->id, 'label' => $note->title])->create();
            }
        }

        $this->client->loginUser($member);
        $this->client->enableProfiler();
        self::getContainer()->get('doctrine')->getManager()->clear();
        self::getContainer()->get('doctrine.debug_data_holder')->reset();
        $this->client->request('GET', '/api/band_spaces/' . $space->id . '/chat/messages');

        $this->assertResponseIsSuccessful();
        $renderedCards = array_sum(array_map(
            static fn (array $message): int => count($message['attachments']),
            $this->getResponseAsArray()['member'],
        ));
        $this->assertSame(50, $renderedCards);

        $profile = $this->client->getProfile();
        $this->assertNotFalse($profile, 'The profiler must be enabled to count the queries.');
        // Measured at 12: the ten a page of ten messages costs once the attachment rows are fetched,
        // which happens on every read, plus one query for the tasks and one for the notes. The margin
        // is for a change to the firewall, not for a per-attachment query, which would put this past
        // sixty.
        $this->assertLessThanOrEqual(
            13,
            $profile->getCollector('db')->getQueryCount(),
            'Resolving attachments must cost one query per kind of target, whatever the number of messages',
        );
    }

    /**
     * @param array<int, array<string, mixed>> $attachments
     *
     * @return array<string, mixed>
     */
    private function expectedMessage(
        object $message,
        BandSpace $space,
        object $author,
        string $content,
        string $creationDatetime,
        array $attachments,
        ?string $editableContent,
    ): array {
        return [
            '@id' => '/api/chat_messages/id=' . $message->id . ';bandSpaceId=' . $space->id,
            '@type' => 'ChatMessage',
            'id' => (string) $message->id,
            'band_space_id' => (string) $space->id,
            'author_id' => (string) $author->id,
            'author_username' => $author->username,
            'author_profile_picture_url' => null,
            'content' => $content,
            'creation_datetime' => $creationDatetime,
            'update_datetime' => null,
            'editable_content' => $editableContent,
            'reactions' => [],
            'attachments' => $attachments,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function expectedCard(string $type, string $targetId, string $label): array
    {
        return [
            'type' => $type,
            'target_id' => $targetId,
            'label' => $label,
            'is_available' => true,
        ];
    }

    private function channelOf(BandSpace $bandSpace): MessageThread
    {
        return MessageThreadFactory::new()->forBandSpace($bandSpace)->create();
    }

    /**
     * @param mixed[] $attachments
     */
    private function post(BandSpace $bandSpace, string $content, array $attachments): void
    {
        $this->client->jsonRequest(
            'POST',
            '/api/band_spaces/' . $bandSpace->id . '/chat/messages',
            ['content' => $content, 'attachments' => $attachments],
            ['CONTENT_TYPE' => 'application/ld+json', 'HTTP_ACCEPT' => 'application/ld+json'],
        );
    }
}
