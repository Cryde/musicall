<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BandSpace\BandSpaceActivityResource;
use App\Entity\BandSpace\BandSpaceActivity;
use App\Entity\User;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\Builder\BandSpace\BandSpaceActivityBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The item route used to share the collection provider, which reads only bandSpaceId, so it answered
 * every single-activity request with the whole paginated feed. The DTO is built through
 * BandSpaceActivityBuilder so the payload keeps going through ActivityPayloadMask.
 *
 * @implements ProviderInterface<BandSpaceActivityResource>
 */
readonly class BandSpaceActivityItemProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private BandSpaceActivityRepository $bandSpaceActivityRepository,
        private BandSpaceActivityBuilder $bandSpaceActivityBuilder,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): BandSpaceActivityResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        // Same membership check as the collection: the feed is open to every member, not just admins.
        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $activity = $this->bandSpaceActivityRepository->findOneByIdAndBandSpace((string) $uriVariables['id'], $bandSpace);
        if (!$activity instanceof BandSpaceActivity) {
            throw new NotFoundHttpException('Activité introuvable');
        }

        return $this->bandSpaceActivityBuilder->buildItem($activity);
    }
}
