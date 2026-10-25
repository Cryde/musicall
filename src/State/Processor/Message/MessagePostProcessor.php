<?php declare(strict_types=1);

namespace App\State\Processor\Message;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Message\MessageCreation;
use App\ApiResource\Message\MessageResource;
use App\Entity\Message\Message;
use App\Entity\Message\MessageFile;
use App\Entity\Message\MessageThread;
use App\Entity\User;
use App\Repository\Message\MessageRepository;
use App\Repository\Message\MessageThreadRepository;
use App\Service\Access\ThreadAccess;
use App\Service\Builder\Message\MessageBuilder;
use App\Service\Message\DirectMessageMediaStore;
use App\Service\Message\StoredVoiceNote;
use App\Service\Procedure\Message\MessageSenderProcedure;
use App\Service\User\Relation\UserContactPolicy;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @implements ProcessorInterface<MessageCreation, MessageResource>
 */
class MessagePostProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly Security                $security,
        private readonly ThreadAccess            $threadAccess,
        private readonly MessageSenderProcedure  $messageSenderProcedure,
        private readonly MessageBuilder          $messageBuilder,
        private readonly MessageThreadRepository $messageThreadRepository,
        private readonly MessageRepository       $messageRepository,
        private readonly UserContactPolicy       $contactPolicy,
        private readonly DirectMessageMediaStore $mediaStore,
        private readonly LoggerInterface         $logger,
        #[Target('message_send')]
        private readonly RateLimiterFactoryInterface $messageSendLimiter,
    ) {
    }

    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): MessageResource
    {
        if (!$this->security->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            throw new AccessDeniedException('common.not_authenticated');
        }
        /** @var User $user */
        $user = $this->security->getUser();
        $this->messageSendLimiter->create($user->getUserIdentifier())->consume()->ensureAccepted();

        // find(), not the joined lookup: MessageThreadItemProvider has already loaded this very thread
        // to denormalize the IRI, so this is an identity map hit and costs no query at all (#986).
        $thread = $this->messageThreadRepository->find($data->thread->id);
        if (!$thread instanceof MessageThread) {
            throw new NotFoundHttpException('message.thread.not_found');
        }

        if (!$this->threadAccess->isOneOfParticipant($thread, $user)) {
            throw new AccessDeniedException('message.thread.not_participant');
        }

        if (!$this->contactPolicy->canPostInThread($thread, $user)) {
            throw new AccessDeniedHttpException(UserContactPolicy::BLOCKED_MESSAGE);
        }

        $message = $data->hasMedia()
            ? $this->sendWithMedia($thread, $user, $data)
            : $this->messageSenderProcedure->processByThread($thread, $user, $data->content);

        // With the sender as viewer, so the message lands on their screen ready to edit (#1245).
        return $this->messageBuilder->buildItem($message, viewer: $user);
    }

    /** An image or a voice note (#1247): nobody receives one from a stranger they never answered. */
    private function sendWithMedia(MessageThread $thread, User $user, MessageCreation $data): Message
    {
        if (!$this->messageRepository->hasMessageFromOtherThan($thread, $user)) {
            throw new AccessDeniedHttpException('message.media.reply_first');
        }

        $image = $data->image instanceof File ? $this->mediaStore->storeImage($data->image, $thread, $user) : null;
        $voiceNote = $data->voiceNote instanceof File ? $this->mediaStore->storeVoiceNote($data->voiceNote, $thread, $user) : null;
        $media = $image ?? $voiceNote?->file;

        // Whitespace beside an image is stored as nothing, as in the band chat, so an image without a
        // caption has one shape and previews as « Pièce jointe ».
        $content = trim($data->content) === '' ? '' : $data->content;

        try {
            return $this->messageSenderProcedure->processByThread(
                $thread,
                $user,
                $content,
                static function (Message $message) use ($image, $voiceNote): void {
                    if ($image instanceof MessageFile) {
                        $message->imageFileId = (string) $image->id;
                    }
                    if ($voiceNote instanceof StoredVoiceNote) {
                        $message->voiceNoteFileId = (string) $voiceNote->file->id;
                        $message->voiceNoteDurationSeconds = $voiceNote->durationSeconds;
                        $message->voiceNotePeaks = $voiceNote->peaks;
                    }
                },
            );
        } catch (\Throwable $e) {
            // Stored before the send, so a message that could not be sent takes its media back out.
            if ($media instanceof MessageFile) {
                $this->discardQuietly($media);
            }
            throw $e;
        }
    }

    private function discardQuietly(MessageFile $media): void
    {
        try {
            $this->mediaStore->discard($media);
        } catch (\Throwable $e) {
            $this->logger->error('Could not discard the media of an unsent direct message', ['file_id' => (string) $media->id, 'exception' => $e]);
        }
    }
}
