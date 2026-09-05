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
use App\Service\Builder\BandSpace\MemberAbsenceBuilder;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<MemberAbsenceResource, MemberAbsenceResource>
 */
readonly class MemberAbsenceUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private MemberAbsenceChecker $memberAbsenceChecker,
        private MemberAbsenceRepository $memberAbsenceRepository,
        private MemberAbsenceBuilder $memberAbsenceBuilder,
        private Security $security,
    ) {
    }

    /**
     * The member an absence belongs to is deliberately not patchable: moving one to somebody else is
     * a delete plus a create, which keeps a second authorization branch out of this path.
     *
     * @param MemberAbsenceResource $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MemberAbsenceResource
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

        // $data is the stored resource with the patch body merged over it, so a field the caller left
        // out already carries its stored value and assigning it back is a no-op Doctrine skips.
        // Assert\Date has already vouched for the shape, and a `Y-m-d` string carries no offset, so
        // there is no longer a day to pin back: the caller's written day is the only one there is.
        $absence->startDate = new DateTimeImmutable($data->startDate);
        $absence->endDate = new DateTimeImmutable($data->endDate);

        // An identical string is ===, which Doctrine's change set computation skips on its own.
        $absence->reason = $data->reason;

        $this->entityManager->flush();

        return $this->memberAbsenceBuilder->buildItem($absence, $actor);
    }
}
