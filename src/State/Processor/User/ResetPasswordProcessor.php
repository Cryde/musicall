<?php

namespace App\State\Processor\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\User\ResetPassword;
use App\Exception\User\ResetPasswordInvalidTokenException;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

readonly class ResetPasswordProcessor implements ProcessorInterface
{
    public function __construct(
        private UserRepository              $userRepository,
        private UserPasswordHasherInterface $userPasswordHasher,
        private EntityManagerInterface      $entityManager,
    ) {
    }

    /**
     * @param ResetPassword $data
     *
     * @throws ResetPasswordInvalidTokenException
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        if (!$user = $this->userRepository->findByTokenAndLimitDatetime($uriVariables['token'])) {
            throw new ResetPasswordInvalidTokenException('Le token n\'est pas valide ou a expiré.');
        }
        $user->setPassword($this->userPasswordHasher->hashPassword($user, $data->password));
        $user->setToken(null);
        $user->setResetRequestDatetime(null);
        $this->entityManager->flush();
    }
}