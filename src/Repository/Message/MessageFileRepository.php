<?php declare(strict_types=1);

namespace App\Repository\Message;

use App\Entity\Message\MessageFile;
use App\Entity\Message\MessageThread;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MessageFile>
 */
class MessageFileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MessageFile::class);
    }

    public function findOneInThread(string $id, MessageThread $thread): ?MessageFile
    {
        return $this->findOneBy(['id' => $id, 'thread' => $thread]);
    }

    /**
     * Which of these files still exist, for a page of messages at once: a message outlives its media
     * when the sender deletes their account, and the bubble then says so.
     *
     * @param list<string> $ids
     *
     * @return list<string>
     */
    public function findLiveIdsAmong(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        /** @var list<array{id: string}> $rows */
        $rows = $this->createQueryBuilder('f')
            ->select('f.id')
            ->where('f.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): string => (string) $row['id'], $rows);
    }

    /**
     * DQL rather than remove() and flush(): this runs after a failed send, when the transaction that
     * failed has closed the entity manager, and a closed manager refuses both. The stored object is the
     * caller's to delete, since no lifecycle event fires here.
     */
    public function deleteById(string $id): void
    {
        $this->getEntityManager()
            ->createQuery('DELETE FROM App\Entity\Message\MessageFile f WHERE f.id = :id')
            ->setParameter('id', $id)
            ->execute();
    }

    /**
     * @return list<MessageFile>
     */
    public function findByUploader(User $user): array
    {
        return $this->findBy(['uploadedBy' => $user]);
    }
}
