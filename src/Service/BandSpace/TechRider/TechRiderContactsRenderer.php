<?php declare(strict_types=1);

namespace App\Service\BandSpace\TechRider;

use App\Entity\Attribute\Instrument;
use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceMembership;
use App\Repository\BandSpace\BandSpaceMembershipRepository;

/**
 * Turns a band's current membership into the lines a contacts item prints.
 *
 * One place, because the API response and whatever renders the export both need the same strings.
 * If the export formatted them itself, the document a venue receives could differ from the one the
 * band proof-read on screen, and nothing would catch it.
 *
 * Rendered on read rather than copied into the item when it is created: a rider that silently goes
 * stale the moment somebody joins or leaves is worse than no rider, and it is the venue who finds
 * out.
 */
readonly class TechRiderContactsRenderer
{
    /** Matches the reference rider's `JEREMY /// Drums`. */
    private const string NAME_SEPARATOR = ' /// ';

    private const string INSTRUMENT_SEPARATOR = ', ';

    public function __construct(
        private BandSpaceMembershipRepository $membershipRepository,
    ) {
    }

    /**
     * @return array{lines: list<string>, emails: list<string>}
     */
    public function render(BandSpace $bandSpace, bool $showEmails): array
    {
        // Active only. Somebody who left is not on the rider, and the default argument here is
        // what keeps them off it.
        $memberships = $this->membershipRepository->findByBandSpace($bandSpace);
        usort($memberships, $this->byDisplayNameThenUsername(...));

        $lines = [];
        $emails = [];
        foreach ($memberships as $membership) {
            $lines[] = $this->line($membership);
            if ($showEmails) {
                $emails[] = $membership->user->email;
            }
        }

        return ['lines' => $lines, 'emails' => $emails];
    }

    /**
     * A member with no instruments prints the name alone. Appending an empty separator would put
     * a dangling `///` on the page of a document sent to strangers.
     */
    private function line(BandSpaceMembership $membership): string
    {
        $instruments = implode(self::INSTRUMENT_SEPARATOR, array_map(
            static fn (Instrument $instrument): string => $instrument->name,
            array_values($membership->instruments->toArray()),
        ));

        return $instruments === ''
            ? $membership->displayName()
            : $membership->displayName() . self::NAME_SEPARATOR . $instruments;
    }

    /**
     * Deterministic, so two reads of the same rider produce the same document. The roster query
     * orders by join date, which would reshuffle the printed page the moment somebody rejoined.
     * Username breaks a tie between two members who chose the same stage name.
     */
    private function byDisplayNameThenUsername(BandSpaceMembership $a, BandSpaceMembership $b): int
    {
        return [$a->displayName(), $a->user->username] <=> [$b->displayName(), $b->user->username];
    }
}
