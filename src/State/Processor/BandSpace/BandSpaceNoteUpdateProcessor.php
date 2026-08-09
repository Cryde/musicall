<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\BandSpaceNote as BandSpaceNoteDTO;
use App\Entity\BandSpace\BandSpaceNote as BandSpaceNoteEntity;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceNoteActivityType;
use App\Repository\BandSpace\BandSpaceNoteRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\Builder\BandSpace\BandSpaceNoteBuilder;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\PreconditionRequiredHttpException;

/**
 * @implements ProcessorInterface<BandSpaceNoteDTO, BandSpaceNoteDTO>
 */
readonly class BandSpaceNoteUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private BandSpaceNoteRepository $bandSpaceNoteRepository,
        private BandSpaceNoteBuilder $bandSpaceNoteBuilder,
        private BandSpaceActivityRecorder $bandSpaceActivityRecorder,
        private Security $security,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param BandSpaceNoteDTO $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): BandSpaceNoteDTO
    {
        /** @var User $user */
        $user = $this->security->getUser();

        [$bandSpace] = $this->memberChecker->checkMemberForWrite((string) $uriVariables['bandSpaceId'], $user);

        $note = $this->bandSpaceNoteRepository->findOneByIdAndBandSpace($data->id, $bandSpace);
        if (!$note instanceof BandSpaceNoteEntity) {
            throw new NotFoundHttpException('Note not found');
        }

        $requestPayload = $this->requestStack->getCurrentRequest()?->toArray() ?? [];

        // Checked before anything is assigned, so a refused write leaves the note exactly as it was
        // found rather than relying on nobody flushing after the exception.
        $writesContent = array_key_exists('content', $requestPayload);
        if ($writesContent) {
            $this->refuseAWriteFromAStaleCopy($note, $data->expectedContentVersion);
        }

        $oldTitle = $note->title;
        $oldEmoji = $note->emoji;
        $oldContent = $note->content;

        if (array_key_exists('title', $requestPayload)) {
            $note->title = $data->title;
        }

        if (array_key_exists('emoji', $requestPayload)) {
            $note->emoji = $data->emoji;
        }

        if ($writesContent) {
            $note->content = $data->content;
        }

        if (array_key_exists('position', $requestPayload)) {
            $note->position = $data->position;
        }

        $note->updateDatetime = new DateTime();

        // A rename and an emoji pick are deliberate single acts, made by leaving a field or by
        // choosing in a picker, so they are recorded every time. Only the body is written by a
        // timer, and only the body is coalesced.
        if ($oldTitle !== $note->title) {
            $this->bandSpaceActivityRecorder->record(
                bandSpace: $bandSpace,
                module: BandSpaceModule::Notes,
                type: BandSpaceNoteActivityType::Renamed,
                resourceId: $note->id,
                actor: $user,
                payload: ['from' => $oldTitle, 'to' => $note->title],
            );
        }

        if ($oldEmoji !== $note->emoji) {
            $this->bandSpaceActivityRecorder->record(
                bandSpace: $bandSpace,
                module: BandSpaceModule::Notes,
                type: BandSpaceNoteActivityType::EmojiChanged,
                resourceId: $note->id,
                actor: $user,
                payload: ['from' => $oldEmoji, 'to' => $note->emoji],
            );
        }

        if ($oldContent !== $note->content) {
            // Only a real change bumps the revision. Re-saving an identical body loses nobody any
            // work, and bumping there would reject the next autosave of everyone else editing.
            ++$note->contentVersion;

            // Coalesced, unlike the two above: this one is written by the two second autosave, so
            // recording each one would bury the whole space's feed under a single writing session.
            $this->bandSpaceActivityRecorder->recordCoalesced(
                bandSpace: $bandSpace,
                module: BandSpaceModule::Notes,
                type: BandSpaceNoteActivityType::ContentUpdated,
                resourceId: (string) $note->id,
                actor: $user,
            );
        }

        $this->entityManager->flush();

        return $this->bandSpaceNoteBuilder->buildItem($note);
    }

    /**
     * A note body is written by a two second autosave timer, so a member who left a stale copy open
     * used to silently replace everything another member had written since, without either of them
     * ever choosing to save and with no history to recover from. A body write therefore has to name
     * the revision it was written against.
     *
     * A missing precondition is refused rather than waved through: a guard that any caller can skip
     * by leaving one field out is not a guard. The endpoint has a single client, our own editor, and
     * it sends the revision back on every body write. Title, emoji and position writes are untouched,
     * so the note tree, the drag reorder and the emoji picker carry nothing extra.
     *
     * Read and write are not atomic here, so two saves landing within the same few milliseconds can
     * still both pass. Closing that would mean locking the row on every batch of keystrokes, and the
     * loss this fixes is measured in minutes.
     */
    private function refuseAWriteFromAStaleCopy(BandSpaceNoteEntity $note, ?int $expectedContentVersion): void
    {
        if ($expectedContentVersion === null) {
            throw new PreconditionRequiredHttpException(
                'Indiquez la version de la note sur laquelle vous avez travaillé pour enregistrer son contenu.'
            );
        }

        if ($expectedContentVersion !== $note->contentVersion) {
            throw new ConflictHttpException(
                'Cette note a été modifiée par un autre membre depuis que vous l\'avez ouverte. Vos modifications n\'ont pas été enregistrées afin de ne pas effacer les siennes.'
            );
        }
    }
}
