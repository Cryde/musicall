<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\BandSpaceNote as BandSpaceNoteDTO;
use App\ApiResource\BandSpace\BandSpaceNoteCreate;
use App\Entity\BandSpace\BandSpaceNote;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceNoteActivityType;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Repository\BandSpace\BandSpaceNoteRepository;
use App\Repository\BandSpace\BandSpaceRepository;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\Builder\BandSpace\BandSpaceNoteBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<BandSpaceNoteCreate, BandSpaceNoteDTO>
 */
readonly class BandSpaceNoteCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceRepository $bandSpaceRepository,
        private BandSpaceMembershipRepository $bandSpaceMembershipRepository,
        private BandSpaceNoteRepository $bandSpaceNoteRepository,
        private BandSpaceNoteBuilder $bandSpaceNoteBuilder,
        private BandSpaceActivityRecorder $bandSpaceActivityRecorder,
        private Security $security,
    ) {
    }

    /**
     * @param BandSpaceNoteCreate $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): BandSpaceNoteDTO
    {
        /** @var User $user */
        $user = $this->security->getUser();

        $bandSpace = $this->bandSpaceRepository->findOneByIdWithMemberships((string) $uriVariables['bandSpaceId']);
        if (!$bandSpace) {
            throw new NotFoundHttpException('Band space not found');
        }

        if (!$this->bandSpaceMembershipRepository->isMember($bandSpace, $user)) {
            throw new AccessDeniedHttpException('You are not a member of this band space');
        }

        $parent = null;
        if ($data->parentId !== null) {
            $parent = $this->bandSpaceNoteRepository->findOneByIdAndBandSpace($data->parentId, $bandSpace);
            if (!$parent) {
                throw new NotFoundHttpException('Parent note not found');
            }
        }

        $note = new BandSpaceNote();
        $note->bandSpace = $bandSpace;
        $note->title = $data->title;
        $note->parent = $parent;
        $note->position = $this->bandSpaceNoteRepository->getNextPosition($bandSpace, $parent);

        $this->entityManager->persist($note);
        $this->entityManager->flush();

        $this->bandSpaceActivityRecorder->record(
            bandSpace: $bandSpace,
            module: BandSpaceModule::Notes,
            type: BandSpaceNoteActivityType::Created,
            resourceId: $note->id,
            actor: $user,
            payload: ['title' => $note->title],
        );
        $this->entityManager->flush();

        return $this->bandSpaceNoteBuilder->buildItem($note);
    }
}
