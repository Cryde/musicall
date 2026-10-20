<?php declare(strict_types=1);

namespace App\State\Processor\User\Block;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\Repository\User\Relation\UserBlockRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * @implements ProcessorInterface<mixed, void>
 */
readonly class UserBlockDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private UserBlockRepository $userBlockRepository,
        private Security $security,
        #[Target('user_block')]
        private RateLimiterFactoryInterface $userBlockLimiter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        $this->userBlockLimiter->create($user->getUserIdentifier())->consume()->ensureAccepted();

        if ($this->userBlockRepository->unblock($user, (string) $uriVariables['userId']) === 0) {
            throw new NotFoundHttpException('user.block.not_blocked');
        }
    }
}
