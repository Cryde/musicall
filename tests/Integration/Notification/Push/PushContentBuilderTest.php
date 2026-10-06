<?php

declare(strict_types=1);

namespace App\Tests\Integration\Notification\Push;

use App\Enum\Notification\NotificationType;
use App\Service\Notification\Push\PushContentBuilder;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\Message\MessageFactory;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\User\UserFactory;
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
        ]);

        $this->assertSame('Les Cactus', $content->title);
        $this->assertSame('alice vous a assigné à la tâche « Répéter le pont »', $content->body);
        $this->assertSame(['type' => 'band_space_task_assignment', 'route' => '/band/b1/tasks/t1'], $content->data);
    }

    public function test_a_chat_mention_opens_the_message(): void
    {
        $content = $this->builder()->forNotification(NotificationType::BandSpaceChatMention, [
            'band_space_id' => 'b1',
            'band_space_name' => 'Les Cactus',
            'message_id' => 'm1',
            'actor_username' => 'alice',
        ]);

        $this->assertSame('alice vous a mentionné dans la discussion de « Les Cactus »', $content->body);
        $this->assertSame(['type' => 'band_space_chat_mention', 'route' => '/band/b1/chat?message=m1'], $content->data);
    }

    public function test_a_role_change_says_which_way_and_opens_the_settings(): void
    {
        $payload = ['band_space_id' => 'b1', 'band_space_name' => 'Les Cactus', 'actor_username' => 'alice'];

        $promoted = $this->builder()->forNotification(NotificationType::BandSpaceRoleChanged, $payload + ['to' => 'admin']);
        $demoted = $this->builder()->forNotification(NotificationType::BandSpaceRoleChanged, $payload + ['to' => 'user']);

        $this->assertSame('alice vous a nommé administrateur de « Les Cactus »', $promoted->body);
        $this->assertSame('alice vous a retiré les droits d\'administrateur sur « Les Cactus »', $demoted->body);
        $this->assertSame(['type' => 'band_space_role_changed', 'route' => '/band/b1/settings'], $promoted->data);
    }

    public function test_a_forum_reply_opens_the_topic(): void
    {
        $content = $this->builder()->forNotification(NotificationType::ForumTopicReply, ['topic_slug' => 'ampli-a-lampes', 'actor_username' => 'bob']);

        $this->assertSame('MusicAll', $content->title);
        $this->assertSame('bob a répondu à votre sujet sur le forum', $content->body);
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
        ]);

        $this->assertSame('alice vous a attribué une dépense sur « Location du van »', $content->body);
        $this->assertSame(['type' => 'band_space_finance_split_assigned'], $content->data);
    }

    public function test_an_invitation_names_its_inviter(): void
    {
        $content = $this->builder()->forNotification(NotificationType::BandSpaceInvitation, [
            'band_space_id' => 'b1',
            'band_space_name' => 'Les Cactus',
            'invited_by_username' => 'alice',
        ]);

        $this->assertSame('alice vous a invité à rejoindre Les Cactus', $content->body);
        $this->assertSame(['type' => 'band_space_invitation'], $content->data);
    }

    public function test_a_direct_message_is_titled_by_its_author_and_opens_the_conversation(): void
    {
        $thread = MessageThreadFactory::new()->create();
        $message = MessageFactory::new(['thread' => $thread, 'content' => "On répète\n\n  mardi ?"])->create();

        $content = $this->builder()->forMessage($message, 'Alice Martin');

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

        $content = $this->builder()->forMessage($message, 'Alice', [(string) $bob->id => 'bob_bass']);

        $this->assertSame('Les Cactus', $content->title);
        $this->assertSame('Alice : Salut @bob_bass !', $content->body);
        $this->assertSame(['type' => 'band_space_message', 'route' => '/band/' . $band->id . '/chat?message=' . $message->id], $content->data);
    }

    public function test_an_attachment_alone_and_a_long_message(): void
    {
        $thread = MessageThreadFactory::new()->create();
        $attachment = MessageFactory::new(['thread' => $thread, 'content' => ''])->create();
        $long = MessageFactory::new(['thread' => $thread, 'content' => str_repeat('a', 200)])->create();

        $this->assertSame('Pièce jointe', $this->builder()->forMessage($attachment, 'Alice')->body);
        $body = $this->builder()->forMessage($long, 'Alice')->body;
        $this->assertSame(140, mb_strlen($body));
        $this->assertStringEndsWith('…', $body);
    }

    private function builder(): PushContentBuilder
    {
        return self::getContainer()->get(PushContentBuilder::class);
    }
}
