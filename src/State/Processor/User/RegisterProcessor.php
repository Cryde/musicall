<?php declare(strict_types=1);

namespace App\State\Processor\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\User\Register;
use App\Entity\User;
use App\Entity\User\UserProfile;
use App\Event\UserRegisteredEvent;
use App\Exception\User\UserAlreadyLoggedException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @implements ProcessorInterface<Register, void>
 */
readonly class RegisterProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $userPasswordHasher,
        private Security $security,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @param Register $data
     *
     * @throws UserAlreadyLoggedException
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        if ($this->security->getUser()) {
            throw new UserAlreadyLoggedException('Vous êtes déjà connecté');
        }

        $user = new User()
            ->setUsername($data->username)
            ->setEmail($data->email)
            ->setPlainPassword($data->password);

        $user->setPassword(
            $this->userPasswordHasher->hashPassword(
                $user,
                $data->password
            )
        );

        $profile = new UserProfile();
        $profile->setUser($user);
        $user->setProfile($profile);

        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $this->eventDispatcher->dispatch(new UserRegisteredEvent($user));
    }
}
