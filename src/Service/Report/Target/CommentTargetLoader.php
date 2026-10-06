<?php declare(strict_types=1);

namespace App\Service\Report\Target;

use App\Entity\Comment\Comment;
use App\Entity\Publication;
use App\Entity\User;
use App\Enum\Report\ReportTargetType;
use App\Repository\Comment\CommentRepository;
use App\Repository\PublicationRepository;
use App\Service\Report\ReportTarget;

/** A comment under an online publication. */
readonly class CommentTargetLoader implements ReportTargetLoaderInterface
{
    public function __construct(
        private CommentRepository $commentRepository,
        private PublicationRepository $publicationRepository,
    ) {
    }

    public function type(): ReportTargetType
    {
        return ReportTargetType::Comment;
    }

    public function load(string $id, User $reporter): ?ReportTarget
    {
        return $this->current($id);
    }

    public function current(string $id): ?ReportTarget
    {
        $comment = ctype_digit($id) ? $this->commentRepository->find((int) $id) : null;
        if (!$comment instanceof Comment) {
            return null;
        }
        $publication = $this->publicationRepository->findOneBy(['thread' => $comment->thread, 'status' => Publication::STATUS_ONLINE]);
        if (!$publication instanceof Publication) {
            return null;
        }

        return new ReportTarget($comment->author, ReportTarget::text($comment->content), [
            'publication_slug' => $publication->slug,
            'publication_title' => $publication->title,
            'is_course' => $publication->subCategory->getIsCourse(),
        ]);
    }
}
