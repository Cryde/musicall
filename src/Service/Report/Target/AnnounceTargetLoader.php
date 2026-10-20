<?php declare(strict_types=1);

namespace App\Service\Report\Target;

use App\Entity\Musician\MusicianAnnounce;
use App\Entity\User;
use App\Enum\Report\ReportTargetType;
use App\Repository\Musician\MusicianAnnounceRepository;
use App\Service\Report\ReportTarget;

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
        return $this->current($id);
    }

    public function current(string $id): ?ReportTarget
    {
        $announce = $this->announceRepository->find($id);
        if (!$announce instanceof MusicianAnnounce || $announce->author->isDeleted()) {
            return null;
        }

        // The instrument by slug (#1212), so it is named in whichever language reads the report.
        return new ReportTarget(
            $announce->author,
            ReportTarget::text((string) $announce->note),
            ['instrument' => $announce->instrument->slug, 'location' => $announce->locationName, 'username' => $announce->author->username],
        );
    }
}
