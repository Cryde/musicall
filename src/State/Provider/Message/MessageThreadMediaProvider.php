<?php declare(strict_types=1);

namespace App\State\Provider\Message;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Message\MessageThreadMedia;
use App\Entity\Message\MessageFile;
use App\Entity\User;
use App\Http\StoredFileResponse;
use App\Repository\Message\MessageFileRepository;
use App\Security\Message\DirectThreadChecker;
use App\Service\BandSpace\Chat\ChatImageConverter;
use App\Service\BandSpace\Chat\ChatVoiceNoteConverter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Vich\UploaderBundle\Storage\StorageInterface;

/**
 * No byte ranges, as in the band chat: the voice note player fetches the whole note into a blob.
 *
 * @implements ProviderInterface<object>
 */
readonly class MessageThreadMediaProvider implements ProviderInterface
{
    /** A day: what a message carries never changes, and a deleted one takes its file with it. */
    public const int CACHE_MAX_AGE_SECONDS = 86400;

    public function __construct(
        private DirectThreadChecker $directThreadChecker,
        private MessageFileRepository $messageFileRepository,
        private StorageInterface $vichStorage,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): StreamedResponse
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$acceptedMimeTypes, $notFoundKey] = $operation->getName() === MessageThreadMedia::VOICE_NOTE_OPERATION
            ? [[ChatVoiceNoteConverter::OUTPUT_MIME_TYPE], 'message.media.voice_note_not_found']
            : [ChatImageConverter::ACCEPTED_MIME_TYPES, 'message.media.image_not_found'];

        // A block closes the conversation's media too, as the issue asks: nothing more reaches either side.
        $thread = $this->directThreadChecker->checkParticipantForWrite((string) $uriVariables['threadId'], $user);

        // The kind is checked too, so an image is never played as a voice note nor the other way round.
        $file = $this->messageFileRepository->findOneInThread((string) $uriVariables['id'], $thread);
        if (!$file instanceof MessageFile || !in_array($file->mimeType, $acceptedMimeTypes, true)) {
            throw new NotFoundHttpException($notFoundKey);
        }

        $response = StoredFileResponse::stream($this->vichStorage, $file, 'uploadedFile', $file->mimeType, $file->originalName, $file->size, inline: true);
        $response->setPrivate();
        $response->setMaxAge(self::CACHE_MAX_AGE_SECONDS);

        return $response;
    }
}
