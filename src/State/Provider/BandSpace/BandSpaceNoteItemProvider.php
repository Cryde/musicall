<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\User;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Repository\BandSpace\BandSpaceNoteRepository;
use App\Repository\BandSpace\BandSpaceRepository;
use App\Service\Builder\BandSpace\BandSpaceNoteBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<object>
 */
readonly class BandSpaceNoteItemProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceRepository $bandSpaceRepository,
        private BandSpaceMembershipRepository $bandSpaceMembershipRepository,
        private BandSpaceNoteRepository $bandSpaceNoteRepository,
        private BandSpaceNoteBuilder $bandSpaceNoteBuilder,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|null|object
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        $bandSpace = $this->bandSpaceRepository->findOneByIdWithMemberships((string) $uriVariables['bandSpaceId']);
        if (!$bandSpace instanceof \App\Entity\BandSpace\BandSpace) {
            throw new NotFoundHttpException('Band space not found');
        }

        if (!$this->bandSpaceMembershipRepository->isMember($bandSpace, $user)) {
            throw new AccessDeniedHttpException('You are not a member of this band space');
        }

        $note = $this->bandSpaceNoteRepository->findOneByIdAndBandSpace((string) $uriVariables['id'], $bandSpace);
        if (!$note instanceof \App\Entity\BandSpace\BandSpaceNote) {
            throw new NotFoundHttpException('Note not found');
        }

        return $this->bandSpaceNoteBuilder->buildItem($note);
    }
}
