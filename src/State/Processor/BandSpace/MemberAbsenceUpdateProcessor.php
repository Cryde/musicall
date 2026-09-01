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
        // out already carries its stored value and assigning it back is a no-op Doctrine skips. The
        // instanceof is the null guard the nullable DTO needs, not a "was it sent" check.
        if ($data->startDate instanceof DateTimeImmutable) {
            $absence->startDate = $this->pinToWrittenDay($data->startDate);
        }

        if ($data->endDate instanceof DateTimeImmutable) {
            $absence->endDate = $this->pinToWrittenDay($data->endDate);
        }

        // An identical string is ===, which Doctrine's change set computation skips on its own.
        $absence->reason = $data->reason;

        $this->entityManager->flush();

        return $this->memberAbsenceBuilder->buildItem($absence, $actor);
    }

    /**
     * The caller's own written day, pinned to midnight UTC.
     *
     * The DTO is denormalized by the loose parser, so an offset the caller sent is still attached and
     * `2026-08-10T23:00:00-04:00` is the 11th in UTC while its wall clock says the 10th. The wall
     * clock wins, because that is the day the member typed. Same rule and same reason as
     * AgendaEntryCreateProcessor's all day branch.
     */
    private function pinToWrittenDay(DateTimeImmutable $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date->format('Y-m-d') . 'T00:00:00+00:00');
    }
}
