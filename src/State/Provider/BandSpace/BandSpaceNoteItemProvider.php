<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\User;
use App\Repository\BandSpace\BandSpaceNoteRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
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
        private BandSpaceMemberChecker $memberChecker,
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

        // The read-only variant: notes stay readable while a deletion is pending, and this provider also
        // runs before the update and delete processors, which carry the write guard themselves.
        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $note = $this->bandSpaceNoteRepository->findOneByIdAndBandSpace((string) $uriVariables['id'], $bandSpace);
        if (!$note instanceof \App\Entity\BandSpace\BandSpaceNote) {
            throw new NotFoundHttpException('Note not found');
        }

        return $this->bandSpaceNoteBuilder->buildItem($note);
    }
}
