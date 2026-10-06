<?php declare(strict_types=1);

namespace App\Service\Report\Target;

use App\Entity\Musician\MusicianProfileMedia;
use App\Entity\Teacher\TeacherProfileMedia;
use App\Entity\User;
use App\Enum\Report\ReportTargetType;
use App\Repository\Musician\MusicianProfileMediaRepository;
use App\Repository\Teacher\TeacherProfileMediaRepository;
use App\Service\Report\ReportTarget;
use Ramsey\Uuid\Uuid;

/** A media on a musician or a teacher profile; both are public and their ids never collide. */
readonly class ProfileMediaTargetLoader implements ReportTargetLoaderInterface
{
    public function __construct(
        private MusicianProfileMediaRepository $musicianMediaRepository,
        private TeacherProfileMediaRepository $teacherMediaRepository,
    ) {
    }

    public function type(): ReportTargetType
    {
        return ReportTargetType::ProfileMedia;
    }

    public function load(string $id, User $reporter): ?ReportTarget
    {
        if (!Uuid::isValid($id)) {
            return null;
        }

        $media = $this->musicianMediaRepository->find($id) ?? $this->teacherMediaRepository->find($id);
        [$owner, $profile] = match (true) {
            $media instanceof MusicianProfileMedia => [$media->musicianProfile->user, 'musician'],
            $media instanceof TeacherProfileMedia => [$media->teacherProfile->user, 'teacher'],
            default => [null, null],
        };
        if (!$owner instanceof User || $owner->isDeleted() || $media === null) {
            return null;
        }

        return new ReportTarget(
            $owner,
            ReportTarget::text((string) $media->title, $media->url),
            ['profile' => $profile, 'username' => $owner->username, 'platform' => $media->platform->value],
        );
    }
}
