<?php

declare(strict_types=1);

namespace App\State\Provider\User\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\User\Profile\UserSocialLinkResource;
use App\Entity\User;
use App\Entity\User\UserSocialLink;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<object>
 */
readonly class UserSocialLinkCollectionProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
    ) {
    }

    /**
     * @return UserSocialLinkResource[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $profile = $user->profile;

        return array_map(
            fn(UserSocialLink $link) => $this->buildFromEntity($link),
            $profile->socialLinks->toArray()
        );
    }

    public function buildFromEntity(UserSocialLink $link): UserSocialLinkResource
    {
        $dto = new UserSocialLinkResource();
        $dto->id = $link->id;
        $dto->platform = $link->platform->value;
        $dto->platformLabel = $link->platform->getLabel();
        $dto->url = $link->url;

        return $dto;
    }
}
