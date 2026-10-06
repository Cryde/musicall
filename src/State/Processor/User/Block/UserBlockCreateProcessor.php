<?php declare(strict_types=1);

namespace App\State\Processor\User\Block;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\User\Block\UserBlockCreate;
use App\ApiResource\User\Block\UserBlockResource;
use App\Entity\User;
use App\Entity\User\Relation\UserBlock;
use App\Repository\User\Relation\UserBlockRepository;
use App\Repository\UserRepository;
use App\Service\Builder\User\Block\UserBlockBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * @implements ProcessorInterface<UserBlockCreate, UserBlockResource>
 */
readonly class UserBlockCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private UserRepository $userRepository,
        private UserBlockRepository $userBlockRepository,
        private UserBlockBuilder $userBlockBuilder,
        private Security $security,
        #[Target('user_block')]
        private RateLimiterFactoryInterface $userBlockLimiter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): UserBlockResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        $this->userBlockLimiter->create($user->getUserIdentifier())->consume()->ensureAccepted();

        $blocked = $this->userRepository->findOneById($data->userId);
        if (!$blocked instanceof User || $blocked->isDeleted()) {
            throw new NotFoundHttpException('Utilisateur introuvable');
        }
        if ($blocked->id === $user->id) {
            throw new UnprocessableEntityHttpException('Vous ne pouvez pas vous bloquer vous-même');
        }

        $this->userBlockRepository->block($user, $blocked);

        // Read back rather than built here, so a repeat answers with the date of the original block.
        $block = $this->userBlockRepository->findOneBy(['blocker' => $user, 'blocked' => $blocked]);
        if (!$block instanceof UserBlock) {
            // Lifted by a concurrent unblock between the two statements.
            throw new NotFoundHttpException('Utilisateur introuvable');
        }

        return $this->userBlockBuilder->build($block);
    }
}
