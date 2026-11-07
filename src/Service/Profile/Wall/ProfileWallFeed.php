<?php

declare(strict_types=1);

namespace App\Service\Profile\Wall;

use App\Entity\Musician\MusicianAnnounce;
use App\Entity\Musician\MusicianProfileMedia;
use App\Entity\Profile\ProfilePost;
use App\Entity\Publication;
use App\Entity\Teacher\TeacherProfileMedia;
use App\Entity\User;
use App\Repository\Musician\MusicianAnnounceRepository;
use App\Repository\Musician\MusicianProfileMediaRepository;
use App\Repository\Profile\ProfilePostRepository;
use App\Repository\PublicationRepository;
use App\Repository\Teacher\TeacherProfileMediaRepository;

/**
 * A member's wall (#1316): their posts and what they did elsewhere on MusicAll, read live from each table and
 * merged by date. Each table gives a page's worth past the cursor, which fills the page whatever the mix.
 */
readonly class ProfileWallFeed
{
    public function __construct(
        private ProfilePostRepository $postRepository,
        private MusicianAnnounceRepository $announceRepository,
        private PublicationRepository $publicationRepository,
        private MusicianProfileMediaRepository $musicianMediaRepository,
        private TeacherProfileMediaRepository $teacherMediaRepository,
    ) {
    }

    /**
     * @return list<WallEntry>
     */
    public function page(User $owner, string $locale, ?WallCursor $before, int $size, ?WallFilter $filter = null): array
    {
        // A filter only skips sources: a cursor still places every row, whichever source it comes from.
        $reads = static fn (WallItemKind $kind): bool => !$filter instanceof WallFilter || $filter->reads($kind);
        $entries = [
            ...!$reads(WallItemKind::Post) ? [] : array_map(
                static fn (ProfilePost $post): WallEntry => new WallEntry(WallItemKind::Post, $post->creationDatetime, $post),
                $this->postRepository->findForWall($owner, $before, $size),
            ),
            ...!$reads(WallItemKind::Announce) ? [] : array_map(
                static fn (MusicianAnnounce $announce): WallEntry => new WallEntry(WallItemKind::Announce, $announce->creationDatetime, $announce),
                $this->announceRepository->findForWall($owner, $before, $size),
            ),
            ...!$reads(WallItemKind::Publication) ? [] : array_map(
                static fn (Publication $publication): WallEntry => new WallEntry(
                    WallItemKind::Publication,
                    $publication->publicationDatetime ?? $publication->creationDatetime,
                    $publication,
                ),
                $this->publicationRepository->findForWall($owner, $locale, $before, $size, coursesOnly: $filter === WallFilter::Course),
            ),
            ...!$reads(WallItemKind::MusicianMedia) ? [] : array_map(
                static fn (MusicianProfileMedia $media): WallEntry => new WallEntry(WallItemKind::MusicianMedia, $media->creationDatetime, $media),
                $this->musicianMediaRepository->findForWall($owner, $before, $size),
            ),
            ...!$reads(WallItemKind::TeacherMedia) ? [] : array_map(
                static fn (TeacherProfileMedia $media): WallEntry => new WallEntry(WallItemKind::TeacherMedia, $media->creationDatetime, $media),
                $this->teacherMediaRepository->findForWall($owner, $before, $size),
            ),
        ];
        usort($entries, static fn (WallEntry $a, WallEntry $b): int => WallCursor::compare($a->cursor, $b->cursor));

        return array_slice($entries, 0, $size);
    }
}
