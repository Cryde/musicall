<?php

declare(strict_types=1);

namespace App\State\Processor\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\User\ChangeUsername;
use App\Entity\User;
use App\Event\UsernameChangedEvent;
use App\Repository\RefreshTokenRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @implements ProcessorInterface<ChangeUsername, void>
 */
readonly class ChangeUsernameProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private RefreshTokenRepository $refreshTokenRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @param ChangeUsername $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $oldUsername = $user->username;
        $newUsername = $data->newUsername;

        if ($oldUsername === $newUsername) {
            throw new UnprocessableEntityHttpException('Le nouveau nom d\'utilisateur doit être différent de l\'actuel.');
        }

        $existingUser = $this->userRepository->findOneBy(['username' => $newUsername]);
        if ($existingUser !== null) {
            throw new UnprocessableEntityHttpException('Ce nom d\'utilisateur est déjà pris.');
        }

        $changedAt = new \DateTimeImmutable();
        // One transaction, so the sessions never point at a username that no longer exists.
        $this->entityManager->wrapInTransaction(function () use ($user, $oldUsername, $newUsername, $changedAt): void {
            $user->username = $newUsername;
            $user->usernameChangedDatetime = $changedAt;
            $this->entityManager->flush();
            $this->refreshTokenRepository->renameUsername($oldUsername, $newUsername);
        });

        $this->eventDispatcher->dispatch(new UsernameChangedEvent(
            $user,
            $oldUsername,
            $newUsername,
            $changedAt,
        ));
    }
}
