<?php declare(strict_types=1);

namespace App\Service\Report\Target;

use App\Entity\Publication;
use App\Entity\User;
use App\Enum\Report\ReportTargetType;
use App\Repository\PublicationRepository;
use App\Service\Report\ReportTarget;

/** An online publication; a video « découverte » goes online without moderation. */
readonly class PublicationTargetLoader implements ReportTargetLoaderInterface
{
    public function __construct(private PublicationRepository $publicationRepository)
    {
    }

    public function type(): ReportTargetType
    {
        return ReportTargetType::Publication;
    }

    public function load(string $id, User $reporter): ?ReportTarget
    {
        $publication = ctype_digit($id) ? $this->publicationRepository->find((int) $id) : null;
        if (!$publication instanceof Publication || $publication->status !== Publication::STATUS_ONLINE) {
            return null;
        }

        return new ReportTarget(
            $publication->author,
            ReportTarget::text($publication->title, (string) $publication->shortDescription),
            ['publication_slug' => $publication->slug, 'is_course' => $publication->subCategory->getIsCourse()],
        );
    }
}
