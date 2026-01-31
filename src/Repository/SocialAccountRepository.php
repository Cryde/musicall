<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SocialAccount;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SocialAccount>
 */
class SocialAccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SocialAccount::class);
    }

    public function findByProviderAndProviderId(string $provider, string $providerId): ?SocialAccount
    {
        return $this->findOneBy([
            'provider' => $provider,
            'providerId' => $providerId,
        ]);
    }

    /**
     * @return SocialAccount[]
     */
    public function findByUser(User $user): array
    {
        return $this->findBy(['user' => $user]);
    }
}
