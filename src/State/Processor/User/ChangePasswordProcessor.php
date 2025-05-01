<?php

namespace App\State\Processor\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\Entity\User\ChangePassword;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ChangePasswordProcessor implements ProcessorInterface
{
    public function __construct(
        readonly private Security                    $security,
        readonly private UserPasswordHasherInterface $userPasswordHasher,
        readonly private EntityManagerInterface      $entityManager
    ) {
    }

    /**
     * @param ChangePassword $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $user->setPassword($this->userPasswordHasher->hashPassword($user, $data->getNewPassword()));
        $this->entityManager->flush();
    }
}