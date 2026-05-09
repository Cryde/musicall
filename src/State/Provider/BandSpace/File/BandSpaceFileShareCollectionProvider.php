<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace\File;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BandSpace\File\BandSpaceFileShareResource;
use App\Entity\User;
use App\Repository\BandSpace\BandSpaceFileShareRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\Builder\BandSpace\File\BandSpaceFileShareBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProviderInterface<BandSpaceFileShareResource>
 */
readonly class BandSpaceFileShareCollectionProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private BandSpaceFileShareRepository $shareRepository,
        private BandSpaceFileShareBuilder $shareBuilder,
        private Security $security,
    ) {
    }

    /**
     * @return BandSpaceFileShareResource[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $now = new \DateTimeImmutable();
        $shares = $this->shareRepository->findActiveByBandSpace($bandSpace, $now);

        return $this->shareBuilder->buildFromList($shares, $now);
    }
}
