<?php declare(strict_types=1);

namespace App\Repository\User\Relation;

use App\Entity\Message\MessageParticipant;
use App\Entity\User;
use App\Entity\User\Relation\UserBlock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserBlock>
 */
class UserBlockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserBlock::class);
    }

    /**
     * DQL condition: the user behind `$userAlias` and the one bound to `:$viewerParameter` have no
     * block between them, whichever of the two placed it. The caller binds the parameter.
     */
    public static function notBlockedEitherWay(string $userAlias, string $viewerParameter): string
    {
        return sprintf(
            'NOT EXISTS (SELECT %2$s_block.id FROM %1$s %2$s_block WHERE (%2$s_block.blocker = :%3$s AND %2$s_block.blocked = %2$s)'
            . ' OR (%2$s_block.blocker = %2$s AND %2$s_block.blocked = :%3$s))',
            UserBlock::class,
            $userAlias,
            $viewerParameter,
        );
    }

    /**
     * DQL condition: the thread behind `$threadAlias` is a band channel, or a direct conversation
     * with nobody the user bound to `:$userParameter` has blocked. The caller binds the parameter.
     */
    public static function threadNotHiddenBy(string $threadAlias, string $userParameter): string
    {
        return sprintf(
            '(%1$s.bandSpace IS NOT NULL OR NOT EXISTS (SELECT %1$s_block.id FROM %2$s %1$s_block, %3$s %1$s_participant'
            . ' WHERE %1$s_participant.thread = %1$s AND %1$s_participant.participant = %1$s_block.blocked'
            . ' AND %1$s_block.blocker = :%4$s))',
            $threadAlias,
            UserBlock::class,
            MessageParticipant::class,
            $userParameter,
        );
    }

    /**
     * Raw SQL so a repeat block is a no-op rather than a duplicate key 500 when two requests race
     * (same reasoning as DeviceTokenRepository::register()); DQL has no INSERT.
     */
    public function block(User $blocker, User $blocked): void
    {
        $this->getEntityManager()->getConnection()->executeStatement(
            <<<'SQL'
                INSERT INTO user_block (id, blocker_id, blocked_id, creation_datetime)
                VALUES (UUID(), :blocker, :blocked, :now)
                ON DUPLICATE KEY UPDATE id = id
                SQL,
            [
                'blocker' => (string) $blocker->id,
                'blocked' => (string) $blocked->id,
                'now' => new \DateTimeImmutable()->format('Y-m-d H:i:s'),
            ],
        );
    }

    /** @return int the number of rows removed, 0 when there was no such block */
    public function unblock(User $blocker, string $blockedId): int
    {
        return (int) $this->getEntityManager()
            ->createQuery('DELETE FROM App\Entity\User\Relation\UserBlock b WHERE b.blocker = :blocker AND b.blocked = :blocked')
            ->setParameter('blocker', $blocker)
            ->setParameter('blocked', $blockedId)
            ->execute();
    }

    public function isBlockedEitherWay(User $first, User $second): bool
    {
        return (bool) $this->createQueryBuilder('b')
            ->select('b.id')
            ->where('(b.blocker = :first AND b.blocked = :second) OR (b.blocker = :second AND b.blocked = :first)')
            ->setParameter('first', $first)
            ->setParameter('second', $second)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * The ids of everybody this user blocked or was blocked by.
     *
     * @return array<string, true> keyed by user id
     */
    public function findIdsBlockedEitherWay(User $user): array
    {
        /** @var list<array{blocker_id: string, blocked_id: string}> $rows */
        $rows = $this->createQueryBuilder('b')
            ->select('IDENTITY(b.blocker) AS blocker_id, IDENTITY(b.blocked) AS blocked_id')
            ->where('b.blocker = :user OR b.blocked = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getArrayResult();

        $ids = [];
        foreach ($rows as $row) {
            $other = $row['blocker_id'] === (string) $user->id ? $row['blocked_id'] : $row['blocker_id'];
            $ids[(string) $other] = true;
        }

        return $ids;
    }

    /**
     * The blocks this user placed, newest first, with what the settings list shows about each person.
     *
     * @return list<UserBlock>
     */
    public function findByBlocker(User $blocker): array
    {
        return $this->createQueryBuilder('b')
            ->addSelect('blocked', 'blocked_profile', 'blocked_picture')
            ->join('b.blocked', 'blocked')
            ->join('blocked.profile', 'blocked_profile')
            ->leftJoin('blocked.profilePicture', 'blocked_picture')
            ->where('b.blocker = :blocker')
            ->setParameter('blocker', $blocker)
            ->orderBy('b.creationDatetime', 'DESC')
            ->addOrderBy('b.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** A closed account keeps none of its blocks, either way: there is nobody left to protect or hide. */
    public function deleteForUser(User $user): void
    {
        $this->getEntityManager()
            ->createQuery('DELETE FROM App\Entity\User\Relation\UserBlock b WHERE b.blocker = :user OR b.blocked = :user')
            ->setParameter('user', $user)
            ->execute();
    }
}
