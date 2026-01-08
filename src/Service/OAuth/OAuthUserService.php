<?php

declare(strict_types=1);

namespace App\Service\OAuth;

use App\Entity\SocialAccount;
use App\Entity\User;
use App\Repository\SocialAccountRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use function Symfony\Component\String\u;

readonly class OAuthUserService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private SocialAccountRepository $socialAccountRepository,
    ) {
    }

    /**
     * @return array{user: User, isNew: bool}|array{error: string}
     */
    public function findOrCreateUser(
        string $provider,
        string $providerId,
        string $email,
        string $username,
        ?User $currentUser = null
    ): array {
        // Check if social account already exists
        $socialAccount = $this->socialAccountRepository->findByProviderAndProviderId($provider, $providerId);

        if ($socialAccount !== null) {
            // User already linked this social account
            return ['user' => $socialAccount->getUser(), 'isNew' => false];
        }

        // If user is logged in, link the social account to their existing account
        if ($currentUser !== null) {
            $this->createSocialAccount($currentUser, $provider, $providerId, $email);

            return ['user' => $currentUser, 'isNew' => false];
        }

        // Check if email already exists (email conflict)
        $existingUser = $this->userRepository->findOneBy(['email' => $email]);

        if ($existingUser !== null) {
            // Email conflict: user must login with password and link in settings
            return ['error' => 'email_exists'];
        }

        // Create new user
        $user = $this->createUser($email, $username);
        $this->createSocialAccount($user, $provider, $providerId, $email);

        return ['user' => $user, 'isNew' => true];
    }

    private function createUser(string $email, string $username): User
    {
        // Ensure username is unique
        $baseUsername = $this->sanitizeUsername($username);
        $finalUsername = $this->ensureUniqueUsername($baseUsername);

        $user = new User();
        $user->setEmail($email);
        $user->setUsername($finalUsername);
        $user->setPassword(null); // Social-only user, no password
        $user->setConfirmationDatetime(new \DateTime()); // Auto-confirmed

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function createSocialAccount(User $user, string $provider, string $providerId, string $email): SocialAccount
    {
        $socialAccount = new SocialAccount();
        $socialAccount->setUser($user);
        $socialAccount->setProvider($provider);
        $socialAccount->setProviderId($providerId);
        $socialAccount->setEmail($email);

        $this->entityManager->persist($socialAccount);
        $this->entityManager->flush();

        return $socialAccount;
    }

    private function sanitizeUsername(string $username): string
    {
        $sanitized = u($username)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9_]/', '')
            ->truncate(50)
            ->toString();

        return $sanitized ?: 'user';
    }

    private function ensureUniqueUsername(string $baseUsername): string
    {
        $username = $baseUsername;
        $counter = 1;

        while ($this->userRepository->findOneBy(['username' => $username]) !== null) {
            $username = $baseUsername . $counter;
            $counter++;
        }

        return $username;
    }
}
