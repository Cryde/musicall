<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\MemberAbsenceCreate;
use App\ApiResource\BandSpace\MemberAbsenceResource;
use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\BandSpace\MemberAbsence;
use App\Entity\User;
use App\Enum\BandSpace\MembershipStatus;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Security\BandSpace\MemberAbsenceChecker;
use App\Service\Builder\BandSpace\MemberAbsenceBuilder;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<MemberAbsenceCreate, MemberAbsenceResource>
 */
readonly class MemberAbsenceCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private MemberAbsenceChecker $memberAbsenceChecker,
        private BandSpaceMembershipRepository $bandSpaceMembershipRepository,
        private MemberAbsenceBuilder $memberAbsenceBuilder,
        private Security $security,
    ) {
    }

    /**
     * @param MemberAbsenceCreate $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MemberAbsenceResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace, $actor] = $this->memberChecker->checkMemberForWrite((string) $uriVariables['bandSpaceId'], $user);

        $target = $this->resolveTarget($data->memberId, $bandSpace, $actor);
        $this->memberAbsenceChecker->assertCanManage($target, $actor);

        $absence = new MemberAbsence();
        $absence->member = $target;
        // ValidAbsenceRange has already accepted both dates as a strict Y-m-d, so parsing cannot fail.
        $absence->startDate = new DateTimeImmutable($data->startDate);
        $absence->endDate = new DateTimeImmutable($data->endDate);
        $absence->reason = $data->reason;

        $this->entityManager->persist($absence);
        $this->entityManager->flush();

        return $this->memberAbsenceBuilder->buildItem($absence, $actor);
    }

    /**
     * The membership the absence is recorded for: the one named, or the caller's own when nothing is.
     * Whether the caller is allowed to name somebody else is the checker's call, made right after.
     */
    private function resolveTarget(?string $memberId, BandSpace $bandSpace, BandSpaceMembership $actor): BandSpaceMembership
    {
        if ($memberId === null || $memberId === '') {
            return $actor;
        }

        $target = $this->bandSpaceMembershipRepository->findOneByIdAndBandSpace($memberId, $bandSpace);
        if (!$target instanceof BandSpaceMembership) {
            throw new NotFoundHttpException('Membre introuvable');
        }

        // A former member keeps the absences they recorded while they were here, as history, but
        // nobody records a new one for them.
        if ($target->status !== MembershipStatus::Active) {
            throw new UnprocessableEntityHttpException('Ce membre ne fait plus partie du Band Space');
        }

        return $target;
    }
}
