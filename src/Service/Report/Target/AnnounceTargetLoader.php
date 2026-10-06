<?php declare(strict_types=1);

namespace App\Service\Report\Target;

use App\Entity\Musician\MusicianAnnounce;
use App\Entity\User;
use App\Enum\Report\ReportTargetType;
use App\Repository\Musician\MusicianAnnounceRepository;
use App\Service\Report\ReportTarget;
use Ramsey\Uuid\Uuid;

readonly class AnnounceTargetLoader implements ReportTargetLoaderInterface
{
    public function __construct(private MusicianAnnounceRepository $announceRepository)
    {
    }

    public function type(): ReportTargetType
    {
        return ReportTargetType::Announce;
    }

    public function load(string $id, User $reporter): ?ReportTarget
    {
        $announce = Uuid::isValid($id) ? $this->announceRepository->find($id) : null;
        if (!$announce instanceof MusicianAnnounce || $announce->author->isDeleted()) {
            return null;
        }

        return new ReportTarget(
            $announce->author,
            ReportTarget::text((string) $announce->note),
            ['instrument' => $announce->instrument->musicianName, 'location' => $announce->locationName],
        );
    }
}
