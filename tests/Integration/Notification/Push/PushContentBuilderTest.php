<?php

declare(strict_types=1);

namespace App\Tests\Integration\Notification\Push;

use App\Enum\Notification\NotificationType;
use App\Service\Notification\Push\PushContentBuilder;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\Message\MessageFactory;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\User\UserFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/** The words follow the app's notification list, and the routes what the same row opens there (#1110). */
#[ResetDatabase]
class PushContentBuilderTest extends KernelTestCase
{
    public function test_a_task_assignment_opens_the_task(): void
    {
        $content = $this->builder()->forNotification(NotificationType::BandSpaceTaskAssignment, [
            'band_space_id' => 'b1',
            'band_space_name' => 'Les Cactus',
            'task_id' => 't1',
            'task_title' => 'Répéter le pont',
            'actor_username' => 'alice',
        ], 'fr');

        $this->assertSame('Les Cactus', $content->title);
        $this->assertSame('alice t\'a assigné à la tâche « Répéter le pont »', $content->body);
        $this->assertSame(['type' => 'band_space_task_assignment', 'route' => '/band/b1/tasks/t1'], $content->data);
    }

    public function test_a_chat_mention_opens_the_message(): void
    {
        $content = $this->builder()->forNotification(NotificationType::BandSpaceChatMention, [
            'band_space_id' => 'b1',
            'band_space_name' => 'Les Cactus',
            'message_id' => 'm1',
            'actor_username' => 'alice',
        ], 'fr');

        $this->assertSame('alice t\'a mentionné dans la discussion de « Les Cactus »', $content->body);
        $this->assertSame(['type' => 'band_space_chat_mention', 'route' => '/band/b1/chat?message=m1'], $content->data);
    }

    public function test_a_role_change_says_which_way_and_opens_the_settings(): void
    {
        $payload = ['band_space_id' => 'b1', 'band_space_name' => 'Les Cactus', 'actor_username' => 'alice'];

        $promoted = $this->builder()->forNotification(NotificationType::BandSpaceRoleChanged, $payload + ['to' => 'admin'], 'fr');
        $demoted = $this->builder()->forNotification(NotificationType::BandSpaceRoleChanged, $payload + ['to' => 'user'], 'fr');

        $this->assertSame('alice t\'a nommé administrateur de « Les Cactus »', $promoted->body);
        $this->assertSame('alice t\'a retiré les droits d\'administrateur sur « Les Cactus »', $demoted->body);
        $this->assertSame(['type' => 'band_space_role_changed', 'route' => '/band/b1/settings'], $promoted->data);
    }

    public function test_a_forum_reply_opens_the_topic(): void
    {
        $content = $this->builder()->forNotification(NotificationType::ForumTopicReply, ['topic_slug' => 'ampli-a-lampes', 'actor_username' => 'bob'], 'fr');

        $this->assertSame('MusicAll', $content->title);
        $this->assertSame('bob a répondu à ton sujet sur le forum', $content->body);
        $this->assertSame(['type' => 'forum_topic_reply', 'route' => '/forums/topic/ampli-a-lampes'], $content->data);
    }

    /** No screen in the app for it: the push opens the app and nothing more. */
    public function test_a_finance_split_has_no_route(): void
    {
        $content = $this->builder()->forNotification(NotificationType::BandSpaceFinanceSplitAssigned, [
            'band_space_id' => 'b1',
            'band_space_name' => 'Les Cactus',
            'entry_label' => 'Location du van',
            'actor_username' => 'alice',
        ], 'fr');

        $this->assertSame('alice t\'a attribué une dépense sur « Location du van »', $content->body);
        $this->assertSame(['type' => 'band_space_finance_split_assigned'], $content->data);
    }

    public function test_an_invitation_names_its_inviter(): void
    {
        $content = $this->builder()->forNotification(NotificationType::BandSpaceInvitation, [
            'band_space_id' => 'b1',
            'band_space_name' => 'Les Cactus',
            'invited_by_username' => 'alice',
        ], 'fr');

        $this->assertSame('alice t\'a invité à rejoindre Les Cactus', $content->body);
        $this->assertSame(['type' => 'band_space_invitation'], $content->data);
    }

    public function test_a_direct_message_is_titled_by_its_author_and_opens_the_conversation(): void
    {
        $thread = MessageThreadFactory::new()->create();
        $message = MessageFactory::new(['thread' => $thread, 'content' => "On répète\n\n  mardi ?"])->create();

        $content = $this->builder()->forMessage($message, 'Alice Martin', 'fr');

        $this->assertSame('Alice Martin', $content->title);
        $this->assertSame('On répète mardi ?', $content->body);
        $this->assertSame(['type' => 'message', 'route' => '/messages/' . $thread->id], $content->data);
    }

    public function test_a_band_message_is_titled_by_the_band_and_names_its_mentions(): void
    {
        $band = BandSpaceFactory::new()->create(['name' => 'Les Cactus']);
        $bob = UserFactory::new()->asBaseUser()->create(['username' => 'bob_bass', 'email' => 'bob.bass@example.com']);
        $channel = MessageThreadFactory::new()->forBandSpace($band)->create();
        $message = MessageFactory::new(['thread' => $channel, 'content' => 'Salut @[' . $bob->id . '] !'])->create();

        $content = $this->builder()->forMessage($message, 'Alice', 'fr', [(string) $bob->id => 'bob_bass']);

        $this->assertSame('Les Cactus', $content->title);
        $this->assertSame('Alice : Salut @bob_bass !', $content->body);
        $this->assertSame(['type' => 'band_space_message', 'route' => '/band/' . $band->id . '/chat?message=' . $message->id], $content->data);
    }

    /** @return iterable<string, array{string, string}> */
    public static function recipientLocales(): iterable
    {
        yield 'French' => ['fr', 'Alice : Salut @inconnu !'];
        yield 'English' => ['en', 'Alice: Salut @unknown !'];
    }

    /** The request here is French: a push follows its recipient's locale, never the sender's (#1226). */
    #[DataProvider('recipientLocales')]
    public function test_a_mention_nobody_resolves_reads_in_the_recipient_locale(string $locale, string $expected): void
    {
        $band = BandSpaceFactory::new()->create(['name' => 'Les Cactus']);
        $channel = MessageThreadFactory::new()->forBandSpace($band)->create();
        $message = MessageFactory::new(['thread' => $channel, 'content' => 'Salut @[6ba7b810-9dad-11d1-80b4-00c04fd430c8] !'])->create();

        $this->assertSame($expected, $this->builder()->forMessage($message, 'Alice', $locale)->body);
    }

    public function test_an_attachment_alone_and_a_long_message(): void
    {
        $thread = MessageThreadFactory::new()->create();
        $attachment = MessageFactory::new(['thread' => $thread, 'content' => ''])->create();
        $long = MessageFactory::new(['thread' => $thread, 'content' => str_repeat('a', 200)])->create();

        $this->assertSame('Pièce jointe', $this->builder()->forMessage($attachment, 'Alice', 'fr')->body);
        $body = $this->builder()->forMessage($long, 'Alice', 'fr')->body;
        $this->assertSame(140, mb_strlen($body));
        $this->assertStringEndsWith('…', $body);
    }

    /**
     * Every sentence renders exactly as the sprintf it replaced (#1191), apostrophes included: in an
     * ICU message a quote can open a literal, which would swallow a placeholder or drop the quote.
     *
     * @param array<string, string> $payload
     */
    #[DataProvider('everySentence')]
    public function test_every_notification_reads_as_before(NotificationType $type, array $payload, string $expected): void
    {
        $actor = ['actor_username' => 'alice', 'band_space_name' => 'Les Cactus'];

        $this->assertSame($expected, $this->builder()->forNotification($type, $payload + $actor, 'fr')->body);
    }

    /** @return iterable<string, array{NotificationType, array<string, string>, string}> */
    public static function everySentence(): iterable
    {
        yield 'invitation' => [NotificationType::BandSpaceInvitation, ['actor_username' => '', 'invited_by_username' => 'alice'], 'alice t\'a invité à rejoindre Les Cactus'];
        yield 'invitation accepted' => [NotificationType::BandSpaceInvitationAccepted, [], 'alice a accepté ton invitation à Les Cactus'];
        yield 'invitation declined' => [NotificationType::BandSpaceInvitationDeclined, [], 'alice a décliné ton invitation à Les Cactus'];
        yield 'task assignment' => [NotificationType::BandSpaceTaskAssignment, ['task_title' => 'Répéter le pont'], 'alice t\'a assigné à la tâche « Répéter le pont »'];
        yield 'task mention' => [NotificationType::TaskMention, ['task_title' => 'Répéter le pont'], 'alice t\'a mentionné dans la tâche « Répéter le pont »'];
        yield 'chat mention' => [NotificationType::BandSpaceChatMention, [], 'alice t\'a mentionné dans la discussion de « Les Cactus »'];
        yield 'task comment' => [NotificationType::TaskComment, ['task_title' => 'Répéter le pont'], 'alice a commenté la tâche « Répéter le pont »'];
        yield 'agenda entry' => [NotificationType::BandSpaceAgendaEntryCreated, ['entry_title' => 'Concert d\'été'], 'alice a ajouté l\'événement « Concert d\'été »'];
        yield 'availability' => [NotificationType::BandSpaceAgendaAvailabilityRequested, ['entry_title' => 'Concert {live}'], 'alice demande tes disponibilités pour « Concert {live} »'];
        yield 'finance split' => [NotificationType::BandSpaceFinanceSplitAssigned, ['entry_label' => 'Location du van'], 'alice t\'a attribué une dépense sur « Location du van »'];
        yield 'promoted' => [NotificationType::BandSpaceRoleChanged, ['to' => 'admin'], 'alice t\'a nommé administrateur de « Les Cactus »'];
        yield 'demoted' => [NotificationType::BandSpaceRoleChanged, ['to' => 'user'], 'alice t\'a retiré les droits d\'administrateur sur « Les Cactus »'];
        yield 'member removed' => [NotificationType::BandSpaceMemberRemoved, [], 'alice t\'a retiré de « Les Cactus »'];
        yield 'member left' => [NotificationType::BandSpaceMemberLeft, [], 'alice a quitté « Les Cactus »'];
        yield 'deletion scheduled' => [NotificationType::BandSpaceDeletionScheduled, [], 'alice a programmé la suppression de « Les Cactus »'];
        yield 'deletion cancelled' => [NotificationType::BandSpaceDeletionCancelled, [], 'alice a annulé la suppression de « Les Cactus »'];
        yield 'forum reply' => [NotificationType::ForumTopicReply, [], 'alice a répondu à ton sujet sur le forum'];
        yield 'publication comment' => [NotificationType::PublicationComment, [], 'alice a commenté ta publication'];
        yield 'comment reply' => [NotificationType::CommentReply, [], 'alice a répondu à ton commentaire'];
        yield 'publication approved' => [NotificationType::PublicationApproved, [], 'alice a publié ton article'];
        yield 'publication rejected' => [NotificationType::PublicationRejected, [], 'alice a refusé ton article'];
        yield 'gallery approved' => [NotificationType::GalleryApproved, [], 'alice a publié ta galerie'];
        yield 'gallery rejected' => [NotificationType::GalleryRejected, [], 'alice a refusé ta galerie'];
        yield 'report received' => [NotificationType::ReportReceived, ['actor_username' => ''], 'a une nouvelle notification pour toi'];
        yield 'report resolved' => [NotificationType::ReportResolved, [], 'alice a une nouvelle notification pour toi'];
        yield 'names with quotes and braces' => [NotificationType::BandSpaceMemberLeft, ['actor_username' => "o'{neil}'", 'band_space_name' => "L''écho {#} '"], "o'{neil}' a quitté « L''écho {#} ' »"];
    }

    private function builder(): PushContentBuilder
    {
        return self::getContainer()->get(PushContentBuilder::class);
    }
}
