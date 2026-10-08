<?php declare(strict_types=1);

namespace App\Service\Notification\Push;

use App\Entity\Message\Message;
use App\Enum\BandSpace\Role;
use App\Enum\Notification\NotificationType;
use App\Service\BandSpace\ChatMentionRenderer;
use App\Service\Message\MessagePlainTextExtractor;

/**
 * The words and the `route` of a push (#1110). The sentences follow the app's notification list word
 * for word (`AppNotification.preview`), and the routes its `destination`: a push opens what the same
 * row in the app opens, and no route means a tap only opens the app.
 */
readonly class PushContentBuilder
{
    private const string DEFAULT_TITLE = 'MusicAll';
    private const string ATTACHMENT_ONLY = 'Pièce jointe';
    private const int BODY_MAX_LENGTH = 140;

    public function __construct(
        private MessagePlainTextExtractor $plainTextExtractor,
        private ChatMentionRenderer $chatMentionRenderer,
    ) {
    }

    /**
     * @param array<string, mixed> $payload as stored on the notification
     */
    public function forNotification(NotificationType $type, array $payload): PushContent
    {
        $text = static fn (string $key): string => is_scalar($payload[$key] ?? null) ? (string) $payload[$key] : '';
        $bandName = $text('band_space_name');

        $sentence = match ($type) {
            NotificationType::BandSpaceInvitation => sprintf('vous a invité à rejoindre %s', $bandName),
            NotificationType::BandSpaceInvitationAccepted => sprintf('a accepté votre invitation à %s', $bandName),
            NotificationType::BandSpaceInvitationDeclined => sprintf('a décliné votre invitation à %s', $bandName),
            NotificationType::BandSpaceTaskAssignment => sprintf('vous a assigné à la tâche « %s »', $text('task_title')),
            NotificationType::TaskMention => sprintf('vous a mentionné dans la tâche « %s »', $text('task_title')),
            NotificationType::BandSpaceChatMention => sprintf('vous a mentionné dans la discussion de « %s »', $bandName),
            NotificationType::TaskComment => sprintf('a commenté la tâche « %s »', $text('task_title')),
            NotificationType::BandSpaceAgendaEntryCreated => sprintf('a ajouté l\'événement « %s »', $text('entry_title')),
            NotificationType::BandSpaceAgendaAvailabilityRequested => sprintf('demande vos disponibilités pour « %s »', $text('entry_title')),
            NotificationType::BandSpaceFinanceSplitAssigned => sprintf('vous a attribué une dépense sur « %s »', $text('entry_label')),
            NotificationType::BandSpaceRoleChanged => $text('to') === Role::Admin->value
                ? sprintf('vous a nommé administrateur de « %s »', $bandName)
                : sprintf('vous a retiré les droits d\'administrateur sur « %s »', $bandName),
            NotificationType::BandSpaceMemberRemoved => sprintf('vous a retiré de « %s »', $bandName),
            NotificationType::BandSpaceMemberLeft => sprintf('a quitté « %s »', $bandName),
            NotificationType::BandSpaceDeletionScheduled => sprintf('a programmé la suppression de « %s »', $bandName),
            NotificationType::BandSpaceDeletionCancelled => sprintf('a annulé la suppression de « %s »', $bandName),
            NotificationType::ForumTopicReply => 'a répondu à votre sujet sur le forum',
            NotificationType::PublicationComment => 'a commenté votre publication',
            NotificationType::CommentReply => 'a répondu à votre commentaire',
            NotificationType::PublicationApproved => 'a publié votre article',
            NotificationType::PublicationRejected => 'a refusé votre article',
            NotificationType::GalleryApproved => 'a publié votre galerie',
            NotificationType::GalleryRejected => 'a refusé votre galerie',
            NotificationType::ReportReceived, NotificationType::ReportResolved => 'a une nouvelle notification pour vous',
        };

        // An invitation predates the actor_username convention and names its inviter under its own key.
        $actor = $text('actor_username') !== '' ? $text('actor_username') : $text('invited_by_username');

        return new PushContent(
            $bandName !== '' ? $bandName : self::DEFAULT_TITLE,
            self::shorten(trim($actor . ' ' . $sentence)),
            self::data($type->value, $this->routeFor($type, $payload)),
        );
    }

    /**
     * A message in a direct conversation or a band channel, for one of its other members.
     *
     * @param array<string, string> $mentionUsernamesById the names the channel's mentions resolve to
     */
    public function forMessage(Message $message, string $authorName, array $mentionUsernamesById = []): PushContent
    {
        $thread = $message->thread;
        $bandSpace = $thread->bandSpace;
        $preview = $this->preview($message, $mentionUsernamesById, $bandSpace !== null);

        if ($bandSpace === null) {
            return new PushContent(
                $authorName,
                self::shorten($preview),
                self::data('message', sprintf('/messages/%s', $thread->id)),
            );
        }

        return new PushContent(
            $bandSpace->name,
            self::shorten(sprintf('%s : %s', $authorName, $preview)),
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
    private function preview(Message $message, array $mentionUsernamesById, bool $isChannel): string
    {
        // Content is empty for a channel message sent for its attachments alone.
        if ($message->content === '') {
            return self::ATTACHMENT_ONLY;
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
