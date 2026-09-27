<?php

declare(strict_types=1);

namespace App\Service\Musician\Match;

use App\Entity\Attribute\Style;
use App\Entity\Musician\MusicianAnnounce;
use App\Entity\User;
use App\Model\Musician\AnnounceMatch;
use App\Repository\Musician\MusicianAnnounceRepository;

/**
 * Which announces answer which (#1082): the mirror type and the same instrument, within 20 km, both
 * younger than two months so a stale announce stops matching. Styles rank the matches, they do not
 * filter them.
 */
readonly class AnnounceMatcher
{
    public const int RADIUS_KM = 20;
    private const string MAX_AGE = '-2 months';
    // A member's latest announces are the ones that still say what they are looking for.
    private const int ANNOUNCES_PER_MEMBER = 3;
    // A new announce notifies synchronously, within its post: the closest this many, not a whole city.
    private const int CANDIDATES_PER_ANNOUNCE = 50;

    public function __construct(
        private MusicianAnnounceRepository $musicianAnnounceRepository,
    ) {
    }

    /**
     * The best matches for a member's latest announces, each announce once.
     *
     * @return list<AnnounceMatch>
     */
    public function matchesFor(User $member, int $limit): array
    {
        $since = $this->since();
        $best = [];
        foreach ($this->musicianAnnounceRepository->findRecentByAuthor($member, $since, self::ANNOUNCES_PER_MEMBER) as $own) {
            foreach ($this->answering($own, $since) as $match) {
                $id = (string) $match->announce->id;
                if (!isset($best[$id]) || $match->ranksBefore($best[$id])) {
                    $best[$id] = $match;
                }
            }
        }

        return array_slice($this->ranked(array_values($best)), 0, $limit);
    }

    /**
     * The announces a new one answers, one per author so nobody hears about it twice. In each match,
     * `announce` is the new one and `answered` the author's.
     *
     * @return list<AnnounceMatch>
     */
    public function answeredBy(MusicianAnnounce $announce): array
    {
        $byAuthor = [];
        foreach ($this->answering($announce, $this->since()) as $match) {
            $answered = new AnnounceMatch($announce, $match->announce, $match->distanceMetres, $match->sharedStyles);
            $authorId = (string) $answered->answered->author->id;
            if (!isset($byAuthor[$authorId]) || $answered->ranksBefore($byAuthor[$authorId])) {
                $byAuthor[$authorId] = $answered;
            }
        }

        return array_values($byAuthor);
    }

    /**
     * @return list<AnnounceMatch>
     */
    private function answering(MusicianAnnounce $announce, \DateTimeInterface $since): array
    {
        $styleNames = $this->styleNames($announce);

        return array_map(
            fn (array $row): AnnounceMatch => new AnnounceMatch(
                $row[0],
                $announce,
                (float) $row['distance'],
                array_values(array_intersect($this->styleNames($row[0]), $styleNames)),
            ),
            $this->musicianAnnounceRepository->findAnswering($announce, $since, self::RADIUS_KM, self::CANDIDATES_PER_ANNOUNCE),
        );
    }

    /**
     * @param list<AnnounceMatch> $matches
     *
     * @return list<AnnounceMatch>
     */
    private function ranked(array $matches): array
    {
        usort($matches, static fn (AnnounceMatch $a, AnnounceMatch $b): int => $a->ranksBefore($b) ? -1 : ($b->ranksBefore($a) ? 1 : 0));

        return $matches;
    }

    /**
     * @return list<string>
     */
    private function styleNames(MusicianAnnounce $announce): array
    {
        return array_values(array_map(static fn (Style $style): string => $style->name, $announce->styles->toArray()));
    }

    private function since(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(self::MAX_AGE);
    }
}
