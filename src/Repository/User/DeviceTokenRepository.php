<?php declare(strict_types=1);

namespace App\Repository\User;

use App\Entity\User;
use App\Entity\User\DeviceToken;
use App\Enum\User\DevicePlatform;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * The deletes below are DQL rather than find and remove: DeviceToken has no lifecycle callbacks, no
 * upload and nothing cascading off it, so the ORM would only hydrate rows to throw them away.
 *
 * @extends ServiceEntityRepository<DeviceToken>
 */
class DeviceTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DeviceToken::class);
    }

    /**
     * Creates the token, refreshes it, or moves it to this user, in one statement. Raw SQL because
     * DQL has no INSERT, and a read then persist would let two concurrent registrations race into a
     * 500 off the unique index (same reasoning as MessageReactionRepository::add()).
     */
    public function register(User $user, string $token, DevicePlatform $platform): void
    {
        $this->getEntityManager()->getConnection()->executeStatement(
            <<<'SQL'
                INSERT INTO device_token (id, user_id, token, platform, creation_datetime, last_seen_datetime)
                VALUES (UUID(), :user, :token, :platform, :now, :now)
                ON DUPLICATE KEY UPDATE user_id = :user, platform = :platform, last_seen_datetime = :now
                SQL,
            [
                'user' => (string) $user->id,
                'token' => $token,
                'platform' => $platform->value,
                'now' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ],
        );
    }

    /** @return list<string> */
    public function findTokensForUser(User $user): array
    {
        return array_values(array_map(strval(...), $this->createQueryBuilder('d')
            ->select('d.token')
            ->where('d.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleColumnResult()));
    }

    /** Answers how many rows went, so a caller can tell "not yours or unknown" from a deletion. */
    public function deleteOneForUser(User $user, string $token): int
    {
        return (int) $this->getEntityManager()
            ->createQuery('DELETE FROM App\Entity\User\DeviceToken d WHERE d.user = :user AND d.token = :token')
            ->setParameter('user', $user)
            ->setParameter('token', $token)
            ->execute();
    }

    public function deleteForUser(User $user): void
    {
        $this->getEntityManager()
            ->createQuery('DELETE FROM App\Entity\User\DeviceToken d WHERE d.user = :user')
            ->setParameter('user', $user)
            ->execute();
    }

    /** @param list<string> $tokens */
    public function deleteByTokens(array $tokens): void
    {
        if ($tokens === []) {
            return;
        }

        $this->getEntityManager()
            ->createQuery('DELETE FROM App\Entity\User\DeviceToken d WHERE d.token IN (:tokens)')
            ->setParameter('tokens', $tokens)
            ->execute();
    }
}
