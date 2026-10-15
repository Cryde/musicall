<?php

declare(strict_types=1);

namespace App\State\Provider\User\Locale;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\User\Locale\UserLocaleEdit;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<UserLocaleEdit>
 */
readonly class UserLocaleEditProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): UserLocaleEdit
    {
        /** @var User $user */
        $user = $this->security->getUser();

        $resource = new UserLocaleEdit();
        $resource->locale = $user->locale;

        return $resource;
    }
}
