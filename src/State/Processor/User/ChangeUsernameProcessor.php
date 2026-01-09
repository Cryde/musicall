<?php

declare(strict_types=1);

namespace App\State\Processor\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\User\ChangeUsername;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<ChangeUsername, void>
 */
readonly class ChangeUsernameProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
    ) {
    }

    /**
     * @param ChangeUsername $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $newUsername = $data->newUsername;

        if ($user->getUsername() === $newUsername) {
            throw new UnprocessableEntityHttpException('Le nouveau nom d\'utilisateur doit être différent de l\'actuel.');
        }

        $existingUser = $this->userRepository->findOneBy(['username' => $newUsername]);
        if ($existingUser !== null) {
            throw new UnprocessableEntityHttpException('Ce nom d\'utilisateur est déjà pris.');
        }

        $user->setUsername($newUsername);
        $user->setUsernameChangedDatetime(new \DateTimeImmutable());
        $this->entityManager->flush();
    }
}
