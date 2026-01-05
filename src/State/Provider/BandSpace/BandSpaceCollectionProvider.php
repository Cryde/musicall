<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\User;
use App\Repository\BandSpace\BandSpaceRepository;
use App\Service\Builder\BandSpace\BandSpaceBuilder;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<object>
 */
readonly class BandSpaceCollectionProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceRepository $bandSpaceRepository,
        private BandSpaceBuilder $bandSpaceBuilder,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|null|object
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $bandSpaces = $this->bandSpaceRepository->findByUser($user);

        return $this->bandSpaceBuilder->buildFromList($bandSpaces);
    }
}
