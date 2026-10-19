<?php declare(strict_types=1);

namespace App\Repository\BandSpace;

use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\BandSpace\BandSpaceOpenedItem;
use App\Enum\BandSpace\BandSpaceSearchResultType;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<BandSpaceOpenedItem>
 */
class BandSpaceOpenedItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BandSpaceOpenedItem::class);
    }

    /**
     * Records an open, or moves an item opened before back to the top, in a single statement: two
     * tabs opening the same item at once cannot both insert and trip the unique key. Raw SQL because
     * DQL has no upsert; the row has no lifecycle to bypass.
     */
    public function recordOpen(BandSpaceMembership $membership, BandSpaceSearchResultType $type, string $resourceId, DateTimeImmutable $openedAt): void
    {
        $this->getEntityManager()->getConnection()->executeStatement(
            'INSERT INTO band_space_opened_item (id, membership_id, type, resource_id, opened_datetime)'
            . ' VALUES (:id, :membership, :type, :resourceId, :openedAt)'
            . ' ON DUPLICATE KEY UPDATE opened_datetime = VALUES(opened_datetime)',
            [
                'id' => Uuid::v7()->toRfc4122(),
                'membership' => (string) $membership->id,
                'type' => $type->value,
                'resourceId' => $resourceId,
                'openedAt' => $openedAt->format('Y-m-d H:i:s'),
            ],
        );
    }

    /**
     * Keeps a member's newest opens and drops the rest, so the table is bounded without a cleanup
     * job. A bulk delete: the rows have no lifecycle events and nothing else holds them.
     */
    public function pruneBeyond(BandSpaceMembership $membership, int $keep): void
    {
        $overflow = $this->createQueryBuilder('o')
            ->select('o.id')
            ->where('o.membership = :membership')
            ->setParameter('membership', $membership)
            ->orderBy('o.openedDatetime', 'DESC')
            ->addOrderBy('o.id', 'DESC')
            ->setFirstResult($keep)
            ->getQuery()
            ->getSingleColumnResult();

        if ($overflow === []) {
            return;
        }

        $this->getEntityManager()
            ->createQuery('DELETE FROM ' . BandSpaceOpenedItem::class . ' o WHERE o.id IN (:ids)')
            ->setParameter('ids', $overflow)
            ->execute();
    }

    /**
     * The member's own history, newest first, the same order pruneBeyond keeps. The date is to the
     * second, so two opens in the same second order by id: arbitrary, and harmless.
     *
     * @param BandSpaceSearchResultType[] $types
     *
     * @return BandSpaceOpenedItem[]
     */
    public function findLatestByMembership(BandSpaceMembership $membership, array $types): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.membership = :membership')
            ->andWhere('o.type IN (:types)')
            ->setParameter('membership', $membership)
            ->setParameter('types', array_map(static fn (BandSpaceSearchResultType $type): string => $type->value, $types))
            ->orderBy('o.openedDatetime', 'DESC')
            ->addOrderBy('o.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Called by every path that takes a member out of a space (leave, kick, account deletion): the
     * membership row survives with a Left or Kicked status, so its cascade never fires. A bulk delete
     * for the same reason as pruneBeyond.
     */
    public function deleteForMembership(BandSpaceMembership $membership): void
    {
        $this->getEntityManager()
            ->createQuery('DELETE FROM ' . BandSpaceOpenedItem::class . ' o WHERE o.membership = :membership')
            ->setParameter('membership', $membership)
            ->execute();
    }
}
