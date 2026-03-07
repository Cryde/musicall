<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\BandSpaceNote;
use App\Entity\User;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Repository\BandSpace\BandSpaceNoteRepository;
use App\Repository\BandSpace\BandSpaceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<BandSpaceNote, void>
 */
readonly class BandSpaceNoteDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceRepository $bandSpaceRepository,
        private BandSpaceMembershipRepository $bandSpaceMembershipRepository,
        private BandSpaceNoteRepository $bandSpaceNoteRepository,
        private Security $security,
    ) {
    }

    /**
     * @param BandSpaceNote $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
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

        $note = $this->bandSpaceNoteRepository->findOneByIdAndBandSpace($data->id, $bandSpace);
        if (!$note) {
            throw new NotFoundHttpException('Note not found');
        }

        $this->entityManager->remove($note);
        $this->entityManager->flush();
    }
}
