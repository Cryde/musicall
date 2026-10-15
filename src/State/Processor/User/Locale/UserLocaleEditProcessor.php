<?php

declare(strict_types=1);

namespace App\State\Processor\User\Locale;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\User\Locale\UserLocaleEdit;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProcessorInterface<UserLocaleEdit, UserLocaleEdit>
 */
readonly class UserLocaleEditProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param UserLocaleEdit $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): UserLocaleEdit
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $user->locale = $data->locale;
        $this->entityManager->flush();

        return $data;
    }
}
