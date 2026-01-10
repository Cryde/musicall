<?php

declare(strict_types=1);

namespace App\State\Processor\User\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User\UserSocialLink;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @implements ProcessorInterface<UserSocialLink, null>
 */
readonly class UserSocialLinkDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        /** @var UserSocialLink $data */
        $this->entityManager->remove($data);
        $this->entityManager->flush();

        return null;
    }
}
