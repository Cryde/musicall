<?php declare(strict_types=1);

namespace App\Service\Notification\Push;

use App\Entity\Message\Message;
use App\Enum\BandSpace\Role;
use App\Enum\Notification\NotificationType;
use App\Service\BandSpace\ChatMentionRenderer;
use App\Service\Message\MessagePlainTextExtractor;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The words and the `route` of a push (#1110). The sentences follow the app's notification list word
 * for word (`AppNotification.preview`), and the routes its `destination`: a push opens what the same
 * row in the app opens, and no route means a tap only opens the app.
 */
readonly class PushContentBuilder
{
    private const string DOMAIN = 'push';
    private const int BODY_MAX_LENGTH = 140;

    public function __construct(
        private MessagePlainTextExtractor $plainTextExtractor,
        private ChatMentionRenderer $chatMentionRenderer,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param array<string, mixed> $payload as stored on the notification
     * @param string               $locale  the recipient's, never the request's (the actor's)
     */
    public function forNotification(NotificationType $type, array $payload, string $locale): PushContent
    {
        $text = static fn (string $key): string => is_scalar($payload[$key] ?? null) ? (string) $payload[$key] : '';
        $bandName = $text('band_space_name');

        $sentenceKey = match ($type) {
            NotificationType::BandSpaceInvitation => 'notification.band_space_invitation',
            NotificationType::BandSpaceInvitationAccepted => 'notification.band_space_invitation_accepted',
            NotificationType::BandSpaceInvitationDeclined => 'notification.band_space_invitation_declined',
            NotificationType::BandSpaceTaskAssignment => 'notification.band_space_task_assignment',
            NotificationType::TaskMention => 'notification.task_mention',
            NotificationType::BandSpaceChatMention => 'notification.band_space_chat_mention',
            NotificationType::TaskComment => 'notification.task_comment',
            NotificationType::BandSpaceAgendaEntryCreated => 'notification.band_space_agenda_entry_created',
            NotificationType::BandSpaceAgendaAvailabilityRequested => 'notification.band_space_agenda_availability_requested',
            NotificationType::BandSpaceFinanceSplitAssigned => 'notification.band_space_finance_split_assigned',
            NotificationType::BandSpaceRoleChanged => $text('to') === Role::Admin->value
                ? 'notification.band_space_role_changed.promoted'
                : 'notification.band_space_role_changed.demoted',
            NotificationType::BandSpaceMemberRemoved => 'notification.band_space_member_removed',
            NotificationType::BandSpaceMemberLeft => 'notification.band_space_member_left',
            NotificationType::BandSpaceDeletionScheduled => 'notification.band_space_deletion_scheduled',
            NotificationType::BandSpaceDeletionCancelled => 'notification.band_space_deletion_cancelled',
            NotificationType::ForumTopicReply => 'notification.forum_topic_reply',
            NotificationType::PublicationComment => 'notification.publication_comment',
            NotificationType::CommentReply => 'notification.comment_reply',
            NotificationType::PublicationApproved => 'notification.publication_approved',
            NotificationType::PublicationRejected => 'notification.publication_rejected',
            NotificationType::GalleryApproved => 'notification.gallery_approved',
            NotificationType::GalleryRejected => 'notification.gallery_rejected',
            NotificationType::ReportReceived, NotificationType::ReportResolved => 'notification.report',
        };

        // An invitation predates the actor_username convention and names its inviter under its own key.
        $actor = $text('actor_username') !== '' ? $text('actor_username') : $text('invited_by_username');
        $sentence = $this->translator->trans($sentenceKey, [
            'actor' => $actor,
            'band' => $bandName,
            'task' => $text('task_title'),
            'event' => $text('entry_title'),
            'expense' => $text('entry_label'),
        ], self::DOMAIN, $locale);

        return new PushContent(
            $bandName !== '' ? $bandName : $this->translator->trans('title.default', [], self::DOMAIN, $locale),
            // Trimmed for a notification without an actor, which leaves the sentence starting with a space.
            self::shorten(trim($sentence)),
            self::data($type->value, $this->routeFor($type, $payload)),
        );
    }

    /**
     * A message in a direct conversation or a band channel, for one of its other members.
     *
     * @param string                $locale               the recipient's, as for {@see forNotification()}
     * @param array<string, string> $mentionUsernamesById the names the channel's mentions resolve to
     */
    public function forMessage(Message $message, string $authorName, string $locale, array $mentionUsernamesById = []): PushContent
    {
        $thread = $message->thread;
        $bandSpace = $thread->bandSpace;
        $preview = $this->preview($message, $mentionUsernamesById, $bandSpace !== null, $locale);

        if ($bandSpace === null) {
            return new PushContent(
                $authorName,
                self::shorten($preview),
                self::data('message', sprintf('/messages/%s', $thread->id)),
            );
        }

        return new PushContent(
            $bandSpace->name,
            self::shorten($this->translator->trans('message.band_channel_body', ['author' => $authorName, 'preview' => $preview], self::DOMAIN, $locale)),
            self::data('band_space_message', sprintf('/band/%s/chat?message=%s', $bandSpace->id, $message->id)),
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function routeFor(NotificationType $type, array $payload): ?string
    {
        if ($type === NotificationType::ForumTopicReply) {
            $slug = $payload['topic_slug'] ?? null;

            return is_string($slug) ? sprintf('/forums/topic/%s', $slug) : '/forums';
        }

        $band = $payload['band_space_id'] ?? null;
        if (!is_string($band)) {
            return null;
        }

        $task = $payload['task_id'] ?? null;
        if (is_string($task)) {
            return sprintf('/band/%s/tasks/%s', $band, $task);
        }

        $message = $payload['message_id'] ?? null;

        return match ($type) {
            NotificationType::BandSpaceAgendaEntryCreated,
            NotificationType::BandSpaceAgendaAvailabilityRequested => sprintf('/band/%s/agenda', $band),
            NotificationType::BandSpaceChatMention => is_string($message)
                ? sprintf('/band/%s/chat?message=%s', $band, $message)
                : sprintf('/band/%s/chat', $band),
            NotificationType::BandSpaceInvitationAccepted, NotificationType::BandSpaceInvitationDeclined,
            NotificationType::BandSpaceMemberLeft, NotificationType::BandSpaceMemberRemoved,
            NotificationType::BandSpaceRoleChanged => sprintf('/band/%s/settings', $band),
            // The app has no screen for these yet, so the push stays inert rather than landing elsewhere.
            default => null,
        };
    }

    /**
     * @param array<string, string> $mentionUsernamesById
     */
    private function preview(Message $message, array $mentionUsernamesById, bool $isChannel, string $locale): string
    {
        // Content is empty for a channel message sent for its attachments alone.
        if ($message->content === '') {
            return $this->translator->trans('message.attachment_only', [], self::DOMAIN, $locale);
        }

        $content = $isChannel
            ? $this->chatMentionRenderer->renderPlain($message->content, $mentionUsernamesById)
            : $message->content;

        return $this->plainTextExtractor->extractOneLine($content);
    }

    private static function shorten(string $text): string
    {
        return mb_strlen($text) > self::BODY_MAX_LENGTH ? mb_substr($text, 0, self::BODY_MAX_LENGTH - 1) . '…' : $text;
    }

    /**
     * @return array<non-empty-string, string>
     */
    private static function data(string $type, ?string $route): array
    {
        return $route === null ? ['type' => $type] : ['type' => $type, 'route' => $route];
    }
}
