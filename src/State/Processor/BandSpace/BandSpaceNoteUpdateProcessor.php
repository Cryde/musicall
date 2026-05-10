<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\BandSpaceNote as BandSpaceNoteDTO;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceNoteActivityType;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Repository\BandSpace\BandSpaceNoteRepository;
use App\Repository\BandSpace\BandSpaceRepository;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\Builder\BandSpace\BandSpaceNoteBuilder;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<BandSpaceNoteDTO, BandSpaceNoteDTO>
 */
readonly class BandSpaceNoteUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceRepository $bandSpaceRepository,
        private BandSpaceMembershipRepository $bandSpaceMembershipRepository,
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

        $bandSpace = $this->bandSpaceRepository->findOneByIdWithMemberships((string) $uriVariables['bandSpaceId']);
        if (!$bandSpace instanceof \App\Entity\BandSpace\BandSpace) {
            throw new NotFoundHttpException('Band space not found');
        }

        if (!$this->bandSpaceMembershipRepository->isMember($bandSpace, $user)) {
            throw new AccessDeniedHttpException('You are not a member of this band space');
        }

        $note = $this->bandSpaceNoteRepository->findOneByIdAndBandSpace($data->id, $bandSpace);
        if (!$note instanceof \App\Entity\BandSpace\BandSpaceNote) {
            throw new NotFoundHttpException('Note not found');
        }

        $requestPayload = $this->requestStack->getCurrentRequest()?->toArray() ?? [];

        $oldTitle = $note->title;
        $oldEmoji = $note->emoji;
        $oldContent = $note->content;

        if (array_key_exists('title', $requestPayload)) {
            $note->title = $data->title;
        }

        if (array_key_exists('emoji', $requestPayload)) {
            $note->emoji = $data->emoji;
        }

        if (array_key_exists('content', $requestPayload)) {
            $note->content = $data->content;
        }

        if (array_key_exists('position', $requestPayload)) {
            $note->position = $data->position;
        }

        $note->updateDatetime = new DateTime();

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
            $this->bandSpaceActivityRecorder->record(
                bandSpace: $bandSpace,
                module: BandSpaceModule::Notes,
                type: BandSpaceNoteActivityType::ContentUpdated,
                resourceId: $note->id,
                actor: $user,
            );
        }

        $this->entityManager->flush();

        return $this->bandSpaceNoteBuilder->buildItem($note);
    }
}
