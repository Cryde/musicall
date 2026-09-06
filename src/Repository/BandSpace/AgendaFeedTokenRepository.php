<?php declare(strict_types=1);

namespace App\Repository\BandSpace;

use App\Entity\BandSpace\AgendaFeedToken;
use App\Entity\BandSpace\BandSpaceMembership;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AgendaFeedToken>
 */
class AgendaFeedTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AgendaFeedToken::class);
    }

    /**
     * The whole aggregate the public feed needs, in one query: the token, the membership behind it
     * (whose status decides whether the feed is still allowed) and the band space (whose name goes
     * into X-WR-CALNAME). The endpoint is unauthenticated and polled, so it does no second lookup.
     */
    public function findOneByTokenHash(string $tokenHash): ?AgendaFeedToken
    {
        return $this->createQueryBuilder('t')
            ->addSelect('m', 'b')
            ->innerJoin('t.membership', 'm')
            ->innerJoin('m.bandSpace', 'b')
            ->where('t.tokenHash = :hash')
            ->setParameter('hash', $tokenHash)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneByMembership(BandSpaceMembership $membership): ?AgendaFeedToken
    {
        return $this->findOneBy(['membership' => $membership]);
    }

    /**
     * Kills the feed of a member on their way out, whether they left, were removed by an admin or
     * deleted their MusicAll account.
     *
     * DQL rather than a find and remove because the row is only ever hydrated to be thrown away, and
     * because the three departure paths would otherwise each carry the same null check. Nothing is
     * lost by bypassing the ORM here: AgendaFeedToken has no lifecycle callbacks, no upload behind
     * it and nothing cascading off it, and no departure path holds the entity.
     *
     * The read side does not depend on this: AgendaFeedDownloadProvider refuses a membership that is
     * not active anyway, so a departure that somehow skipped this call still closes the feed.
     */
    public function deleteForMembership(BandSpaceMembership $membership): void
    {
        $this->getEntityManager()
            ->createQuery('DELETE FROM App\Entity\BandSpace\AgendaFeedToken t WHERE t.membership = :membership')
            ->setParameter('membership', $membership)
            ->execute();
    }
}
