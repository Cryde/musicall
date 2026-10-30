<?php declare(strict_types=1);

namespace App\Service\BandSpace\Lyrics;

use App\Entity\BandSpace\BandSpaceMembership;
use App\Enum\BandSpace\MembershipStatus;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Service\BandSpace\Song\ChordPro\ChordProParser;
use App\Translation\DisplayNameTranslator;

/** Who the lyrics name, by the name the band knows them by: a member who left keeps their name, marked as former. */
readonly class LyricsSingerResolver
{
    public function __construct(
        private ChordProParser $parser,
        private BandSpaceMembershipRepository $membershipRepository,
        private DisplayNameTranslator $displayNames,
    ) {
    }

    /**
     * The singers in the order the lyrics first name them, which is the order that picks colours.
     *
     * @return list<array{id: string, name: string, is_former_member: bool}>
     */
    public function singers(string $bandSpaceId, ?string $content): array
    {
        $singerIds = array_values(array_filter(
            $this->parser->singerIds($content),
            static fn (string $id): bool => $id !== ChordProParser::SINGER_ALL && uuid_is_valid($id),
        ));

        $byUser = [];
        foreach ($this->membershipRepository->findByBandSpaceIdAndUserIds($bandSpaceId, $singerIds) as $membership) {
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
                    'name' => $this->displayNames->memberName($byUser[$id]),
                    'is_former_member' => $byUser[$id]->status !== MembershipStatus::Active,
                ];
            }
        }

        return $singers;
    }
}
