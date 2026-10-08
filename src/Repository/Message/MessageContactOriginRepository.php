<?php declare(strict_types=1);

namespace App\Repository\Message;

use App\Entity\Message\MessageContactOrigin;
use App\Entity\Message\MessageThread;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MessageContactOrigin>
 */
class MessageContactOriginRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MessageContactOrigin::class);
    }

    /**
     * @param string[] $messageIds
     *
     * @return array<string, MessageContactOrigin> message id => origin
     */
    public function findByMessageIds(array $messageIds): array
    {
        if ($messageIds === []) {
            return [];
        }

        /** @var MessageContactOrigin[] $origins */
        $origins = $this->createQueryBuilder('o')
            ->addSelect('m')
            ->join('o.message', 'm')
            ->where('m.id IN (:ids)')
            ->setParameter('ids', $messageIds)
            ->getQuery()
            ->getResult();

        $byMessage = [];
        foreach ($origins as $origin) {
            $byMessage[(string) $origin->message->id] = $origin;
        }

        return $byMessage;
    }

    /**
     * The most recent origin of each thread, for the inbox rows. Origins are rare (one per contact
     * from an announce or a profile), so reading them all and keeping the newest stays cheap.
     *
     * @param MessageThread[] $threads
     *
     * @return array<string, MessageContactOrigin> thread id => origin
     */
    public function findLatestByThreads(array $threads): array
    {
        if ($threads === []) {
            return [];
        }

        /** @var MessageContactOrigin[] $origins */
        $origins = $this->createQueryBuilder('o')
            ->addSelect('m')
            ->join('o.message', 'm')
            ->where('m.thread IN (:threads)')
            ->setParameter('threads', $threads)
            ->orderBy('m.creationDatetime', 'DESC')
            ->addOrderBy('m.id', 'DESC')
            ->getQuery()
            ->getResult();

        $latest = [];
        foreach ($origins as $origin) {
            $latest[(string) $origin->message->thread->id] ??= $origin;
        }

        return $latest;
    }
}
