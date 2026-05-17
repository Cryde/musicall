<?php

declare(strict_types=1);

namespace App\Procedure\Publication;

use App\Entity\Comment\CommentThread;
use App\Entity\Publication;
use App\Repository\PublicationRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Hard-deletes a publication and every piece of data attached to it:
 * - PublicationImage rows + their files (Vich removes files via lifecycle listener)
 * - PublicationCover + its file (Doctrine cascade on the Publication relation)
 * - ViewCache + every View row (Doctrine cascade + DB FK ON DELETE CASCADE)
 * - VoteCache + every Vote row (Doctrine cascade + DB FK ON DELETE CASCADE)
 * - All Comments in the thread, each with its own VoteCache + Vote rows
 * - The CommentThread itself
 * - map_publication_tag M2M rows (DB FK ON DELETE CASCADE)
 *
 * Author, SubCategory, and the Tag entities themselves are preserved.
 */
readonly class PublicationDeleteProcedure
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PublicationRepository  $publicationRepository,
    ) {
    }

    public function delete(Publication $publication): void
    {
        $publicationId = (int) $publication->id;

        // Reset the unit of work and re-fetch the publication. This avoids Doctrine raising
        // ORMInvalidArgumentException for View/Vote entities that may be lingering in the
        // identity map and pointing to caches we're about to cascade-remove. In production
        // it's a no-op; in tests it sidesteps Foundry-seeded metric rows.
        $this->entityManager->clear();
        $publication = $this->publicationRepository->find($publicationId);
        if (!$publication instanceof Publication) {
            return;
        }

        // Capture the thread before removing the publication: Publication.thread is M2O without
        // cascade=remove, so the CommentThread survives the publication's deletion and must be
        // cleaned up separately.
        $thread = $publication->thread;

        // PublicationImage is OneToMany without cascade=remove on the publication side.
        // Removing each entity via the EM ensures Vich's lifecycle listener wipes the file.
        foreach ($publication->images->toArray() as $image) {
            $this->entityManager->remove($image);
        }

        $this->entityManager->remove($publication);
        $this->entityManager->flush();

        if (!$thread instanceof CommentThread) {
            return;
        }

        // The thread is orphan now. Comments must go via the EM (not bulk DQL) so each
        // comment's voteCache cascades and its Vote rows die at the DB level.
        foreach ($thread->comments->toArray() as $comment) {
            $this->entityManager->remove($comment);
        }
        $this->entityManager->remove($thread);
        $this->entityManager->flush();
    }
}
