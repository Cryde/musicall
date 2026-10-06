<?php declare(strict_types=1);

namespace App\State\Processor\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\ValidatorInterface;
use App\ApiResource\User\Register;
use App\Entity\User;
use App\Entity\User\UserProfile;
use App\Event\UserRegisteredEvent;
use App\Exception\User\UserAlreadyLoggedException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
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
        #[Target('registration')]
        private RateLimiterFactoryInterface $registrationLimiter,
        #[Target('registration_attempt')]
        private RateLimiterFactoryInterface $registrationAttemptLimiter,
        private ValidatorInterface $validator,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param Register $data
     *
     * @throws UserAlreadyLoggedException
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        if ($this->security->getUser() instanceof \Symfony\Component\Security\Core\User\UserInterface) {
            throw new UserAlreadyLoggedException('Vous êtes déjà connecté');
        }

        $ip = $this->requestStack->getCurrentRequest()?->getClientIp() ?? 'unknown';
        // Every attempt, valid or not, before validation can answer whether an email is taken.
        $this->registrationAttemptLimiter->create($ip)->consume()->ensureAccepted();
        $this->validator->validate($data, $operation->getValidationContext() ?? []);
        $this->registrationLimiter->create($ip)->consume()->ensureAccepted();

        $user = new User();
        $user->username = $data->username;
        $user->email = $data->email;
        $user->plainPassword = $data->password;

        $user->password = $this->userPasswordHasher->hashPassword(
            $user,
            $data->password
        );

        $profile = new UserProfile();
        $user->profile = $profile;

        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $this->eventDispatcher->dispatch(new UserRegisteredEvent($user));
    }
}
