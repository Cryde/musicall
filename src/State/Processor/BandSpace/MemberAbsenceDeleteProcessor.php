<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\MemberAbsenceResource;
use App\Entity\BandSpace\MemberAbsence;
use App\Entity\User;
use App\Repository\BandSpace\MemberAbsenceRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Security\BandSpace\MemberAbsenceChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<MemberAbsenceResource, void>
 */
readonly class MemberAbsenceDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private MemberAbsenceChecker $memberAbsenceChecker,
        private MemberAbsenceRepository $memberAbsenceRepository,
        private Security $security,
    ) {
    }

    /**
     * @param MemberAbsenceResource $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace, $actor] = $this->memberChecker->checkMemberForWrite((string) $uriVariables['bandSpaceId'], $user);

        $absence = $this->memberAbsenceRepository->findOneByIdAndBandSpace((string) $uriVariables['id'], $bandSpace);
        if (!$absence instanceof MemberAbsence) {
            throw new NotFoundHttpException('Indisponibilité introuvable');
        }

        $this->memberAbsenceChecker->assertCanManage($absence->member, $actor);

        $this->entityManager->remove($absence);
        $this->entityManager->flush();
    }
}
