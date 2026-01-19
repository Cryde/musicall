<?php

declare(strict_types=1);

namespace App\State\Processor\User\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\User\Profile\UserSocialLinkResource;
use App\Entity\User;
use App\Entity\User\UserSocialLink;
use App\Enum\SocialPlatform;
use App\State\Provider\User\Profile\UserSocialLinkCollectionProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * @implements ProcessorInterface<UserSocialLinkResource, UserSocialLinkResource>
 */
readonly class UserSocialLinkPostProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private EntityManagerInterface $entityManager,
        private UserSocialLinkCollectionProvider $collectionProvider,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): UserSocialLinkResource
    {
        /** @var UserSocialLinkResource $data */
        /** @var User $user */
        $user = $this->security->getUser();
        $profile = $user->getProfile();
        assert($profile !== null);

        $platform = SocialPlatform::tryFrom($data->platform);
        if (!$platform) {
            throw new BadRequestHttpException('Plateforme invalide');
        }

        // Check if platform already exists for this profile
        foreach ($profile->getSocialLinks() as $existingLink) {
            if ($existingLink->getPlatform() === $platform) {
                throw new ConflictHttpException('Un lien pour cette plateforme existe déjà');
            }
        }

        $link = new UserSocialLink();
        $link->setProfile($profile);
        $link->setPlatform($platform);
        $link->setUrl($data->url);

        $this->entityManager->persist($link);
        $this->entityManager->flush();

        return $this->collectionProvider->buildFromEntity($link);
    }
}
