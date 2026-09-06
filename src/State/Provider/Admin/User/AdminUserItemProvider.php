<?php declare(strict_types=1);

namespace App\State\Provider\Admin\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Admin\User\AdminUser;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Builder\Admin\User\AdminUserBuilder;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<AdminUser>
 */
readonly class AdminUserItemProvider implements ProviderInterface
{
    public function __construct(
        private UserRepository $userRepository,
        private AdminUserBuilder $builder,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AdminUser
    {
        $user = $this->userRepository->findOneById((string) $uriVariables['id']);
        if (!$user instanceof User) {
            throw new NotFoundHttpException('Utilisateur introuvable');
        }

        // A deleted account is still shown: it is anonymised rather than removed, and an admin
        // looking into a report needs to see it rather than get a 404.
        return $this->builder->buildWithActivity($user);
    }
}
