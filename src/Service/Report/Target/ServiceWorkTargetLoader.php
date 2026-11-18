<?php

declare(strict_types=1);

namespace App\Service\Report\Target;

use App\Entity\Service\ServiceWork;
use App\Entity\User;
use App\Enum\Report\ReportTargetType;
use App\Repository\Service\ServiceWorkRepository;
use App\Service\Report\ReportTarget;

/** One of a service's « Réalisations » (#1407): reportable by whoever can read the service's page. */
readonly class ServiceWorkTargetLoader implements ReportTargetLoaderInterface
{
    public function __construct(private ServiceWorkRepository $serviceWorkRepository)
    {
    }

    public function type(): ReportTargetType
    {
        return ReportTargetType::ServiceWork;
    }

    public function load(string $id, User $reporter): ?ReportTarget
    {
        $work = $this->serviceWorkRepository->find($id);
        // A draft's works are its owner's alone (#1406), and a private profile hides its services.
        if (
            !$work instanceof ServiceWork
            || !$work->service->isPublished()
            || !$work->service->user->isPubliclyVisible()
            || !$work->service->user->profile->isPublic
        ) {
            return null;
        }

        return $this->target($work);
    }

    public function current(string $id): ?ReportTarget
    {
        $work = $this->serviceWorkRepository->find($id);
        if (!$work instanceof ServiceWork || $work->service->user->isDeleted()) {
            return null;
        }

        return $this->target($work);
    }

    private function target(ServiceWork $work): ReportTarget
    {
        $owner = $work->service->user;

        return new ReportTarget(
            $owner,
            ReportTarget::text($work->title, (string) $work->credit, (string) $work->year, (string) $work->linkUrl, (string) $work->mediaUrl),
            ['username' => $owner->username, 'kind' => $work->service->kind->value, 'image_count' => $work->images->count()],
        );
    }
}
