<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BandSpace\MemberAbsenceResource;
use App\Entity\BandSpace\MemberAbsence;
use App\Entity\User;
use App\Repository\BandSpace\MemberAbsenceRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\Builder\BandSpace\MemberAbsenceBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<object>
 */
readonly class MemberAbsenceItemProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private MemberAbsenceRepository $memberAbsenceRepository,
        private MemberAbsenceBuilder $memberAbsenceBuilder,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): MemberAbsenceResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace, $viewer] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $absence = $this->memberAbsenceRepository->findOneByIdAndBandSpace((string) $uriVariables['id'], $bandSpace);
        if (!$absence instanceof MemberAbsence) {
            throw new NotFoundHttpException('Indisponibilité introuvable');
        }

        return $this->memberAbsenceBuilder->buildItem($absence, $viewer);
    }
}
