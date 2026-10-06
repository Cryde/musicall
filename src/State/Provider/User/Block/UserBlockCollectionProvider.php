<?php declare(strict_types=1);

namespace App\State\Provider\User\Block;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\User\Block\UserBlockResource;
use App\Entity\User;
use App\Repository\User\Relation\UserBlockRepository;
use App\Service\Builder\User\Block\UserBlockBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProviderInterface<UserBlockResource>
 */
readonly class UserBlockCollectionProvider implements ProviderInterface
{
    public function __construct(
        private UserBlockRepository $userBlockRepository,
        private UserBlockBuilder $userBlockBuilder,
        private Security $security,
    ) {
    }

    /** @return list<UserBlockResource> */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        return $this->userBlockBuilder->buildList($this->userBlockRepository->findByBlocker($user));
    }
}
