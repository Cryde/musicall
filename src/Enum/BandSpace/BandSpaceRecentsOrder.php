<?php declare(strict_types=1);

namespace App\Enum\BandSpace;

/**
 * The two orders the command palette can open on, before anything is typed (#1047). A query of its
 * own sorts the hits by kind and ignores this.
 */
enum BandSpaceRecentsOrder: string
{
    /** What the band created or edited last (#1046). */
    case Updated = 'updated';

    /** What the caller opened last, from their own history. */
    case Opened = 'opened';

    /**
     * The values, for the search endpoint's `order` constraint.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $order): string => $order->value, self::cases());
    }
}
