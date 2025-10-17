<?php declare(strict_types=1);

namespace App\State\Processor\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\User\Register;
use App\ApiResource\User\ResetPassword;
use App\Entity\User;
use App\Event\UserRegisteredEvent;
use App\Exception\User\ResetPasswordInvalidTokenException;
use App\Exception\User\UserAlreadyLoggedException;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

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
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
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

        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $this->eventDispatcher->dispatch(new UserRegisteredEvent($user));
    }
}
