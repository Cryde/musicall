<?php declare(strict_types=1);

namespace App\Service\Builder\BandSpace;

use App\ApiResource\BandSpace\Setlist\Song\SongLyrics;
use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\BandSpace\Song;
use App\Enum\BandSpace\MembershipStatus;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Service\BandSpace\Song\ChordPro\ChordProParser;

readonly class SongLyricsBuilder
{
    public function __construct(
        private ChordProParser $parser,
        private BandSpaceMembershipRepository $membershipRepository,
    ) {
    }

    public function build(Song $song): SongLyrics
    {
        $dto = new SongLyrics();
        $dto->id = (string) $song->id;
        $dto->bandSpaceId = (string) $song->bandSpace->id;
        $dto->lyrics = $song->lyrics;
        $dto->lyricsVersion = $song->lyricsVersion;
        $dto->tonality = $song->tonality;
        $dto->singers = $this->singers($song);

        return $dto;
    }

    /**
     * The singers in the order the lyrics first name them, which is the order that picks colours.
     *
     * @return list<array{id: string, name: string, is_former_member: bool}>
     */
    private function singers(Song $song): array
    {
        $singerIds = array_values(array_filter(
            $this->parser->singerIds($song->lyrics),
            static fn (string $id): bool => $id !== ChordProParser::SINGER_ALL && uuid_is_valid($id),
        ));

        $byUser = [];
        foreach ($this->membershipRepository->findByBandSpaceIdAndUserIds((string) $song->bandSpace->id, $singerIds) as $membership) {
            // Someone who left and came back has two memberships; the active one says they are here.
            $current = $byUser[$membership->user->id] ?? null;
            if (!$current instanceof BandSpaceMembership || $membership->status === MembershipStatus::Active) {
                $byUser[$membership->user->id] = $membership;
            }
        }

        $singers = [];
        foreach ($singerIds as $id) {
            if (isset($byUser[$id])) {
                $singers[] = [
                    'id' => $id,
                    'name' => $byUser[$id]->displayName(),
                    'is_former_member' => $byUser[$id]->status !== MembershipStatus::Active,
                ];
            }
        }

        return $singers;
    }
}
