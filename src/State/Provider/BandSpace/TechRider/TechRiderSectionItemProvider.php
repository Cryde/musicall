<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace\TechRider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BandSpace\TechRider\TechRiderSectionResource;
use App\Entity\BandSpace\TechRider;
use App\Entity\BandSpace\TechRiderSection;
use App\Entity\User;
use App\Repository\BandSpace\TechRiderRepository;
use App\Repository\BandSpace\TechRiderSectionRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\Builder\BandSpace\TechRider\TechRiderSectionBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Serves PATCH only, and that is the point: without it a content-only merge-patch would be
 * applied to an empty resource, so `title` would arrive null and fail its NotBlank check.
 *
 * @implements ProviderInterface<TechRiderSectionResource>
 */
readonly class TechRiderSectionItemProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private TechRiderRepository $techRiderRepository,
        private TechRiderSectionRepository $sectionRepository,
        private TechRiderSectionBuilder $sectionBuilder,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?TechRiderSectionResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $techRider = $this->techRiderRepository->findOneByIdAndBandSpace((string) $uriVariables['riderId'], $bandSpace);
        if (!$techRider instanceof TechRider) {
            throw new NotFoundHttpException('Tech rider introuvable');
        }

        $section = $this->sectionRepository->findOneByIdAndRider((string) $uriVariables['id'], $techRider);
        if (!$section instanceof TechRiderSection) {
            throw new NotFoundHttpException('Section introuvable');
        }

        return $this->sectionBuilder->buildItem($section);
    }
}
