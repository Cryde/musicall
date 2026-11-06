<?php

declare(strict_types=1);

namespace App\Service\Builder\Profile;

use App\ApiResource\Profile\ProfilePostResource;
use App\ApiResource\Profile\ProfileWallItem;
use App\ApiResource\Profile\ProfileWallMedia;
use App\Entity\Musician\MusicianAnnounce;
use App\Entity\Musician\MusicianProfileMedia;
use App\Entity\Profile\ProfilePost;
use App\Entity\Publication;
use App\Entity\Teacher\TeacherProfileMedia;
use App\Entity\User;
use App\Service\Builder\Musician\MusicianProfileMediaResourceBuilder;
use App\Service\Builder\Publication\PublicationListItemBuilder;
use App\Service\Builder\Teacher\TeacherProfileMediaResourceBuilder;
use App\Service\Builder\User\PublicProfileAnnounceBuilder;
use App\Service\Profile\Wall\WallEntry;
use Symfony\Contracts\Translation\TranslatorInterface;

/** The lines of a wall page, each built the way the page that owns its content builds it. */
readonly class ProfileWallItemBuilder
{
    public function __construct(
        private ProfilePostResourceBuilder $postBuilder,
        private PublicProfileAnnounceBuilder $announceBuilder,
        private PublicationListItemBuilder $publicationBuilder,
        private MusicianProfileMediaResourceBuilder $musicianMediaBuilder,
        private TeacherProfileMediaResourceBuilder $teacherMediaBuilder,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param list<WallEntry> $entries
     *
     * @return list<ProfileWallItem>
     */
    public function buildAll(array $entries, ?User $viewer): array
    {
        // The posts together, so their likes and comments come in one go.
        $posts = array_values(array_filter(
            array_map(static fn (WallEntry $entry): ?ProfilePost => $entry->row instanceof ProfilePost ? $entry->row : null, $entries),
        ));
        $postResources = [];
        foreach ($this->postBuilder->buildAll($posts, $viewer) as $resource) {
            $postResources[$resource->id] = $resource;
        }

        return array_map(fn (WallEntry $entry): ProfileWallItem => $this->item($entry, $postResources), $entries);
    }

    /**
     * @param array<string, ProfilePostResource> $postResources
     */
    private function item(WallEntry $entry, array $postResources): ProfileWallItem
    {
        $item = new ProfileWallItem();
        $item->cursor = $entry->cursor->toString();
        $item->date = $entry->date;
        $row = $entry->row;
        if ($row instanceof ProfilePost) {
            $item->type = ProfileWallItem::TYPE_POST;
            $item->post = $postResources[(string) $row->id];
        } elseif ($row instanceof MusicianAnnounce) {
            $item->type = ProfileWallItem::TYPE_ANNOUNCE;
            $item->announce = $this->announceBuilder->build($row);
        } elseif ($row instanceof Publication) {
            $item->type = $row->subCategory->getIsCourse() ? ProfileWallItem::TYPE_COURSE : ProfileWallItem::TYPE_PUBLICATION;
            $item->publication = $this->publicationBuilder->buildItem($row);
        } else {
            $item->type = ProfileWallItem::TYPE_MEDIA;
            $item->media = $this->media($row);
        }

        return $item;
    }

    private function media(MusicianProfileMedia|TeacherProfileMedia $entity): ProfileWallMedia
    {
        // The profile's own builders, for the thumbnail they already know how to find.
        $thumbnailUrl = $entity instanceof MusicianProfileMedia
            ? $this->musicianMediaBuilder->buildFromEntity($entity)->thumbnailUrl
            : $this->teacherMediaBuilder->buildFromEntity($entity)->thumbnailUrl;

        $media = new ProfileWallMedia();
        $media->id = (string) $entity->id;
        $media->profile = $entity instanceof MusicianProfileMedia ? ProfileWallMedia::PROFILE_MUSICIAN : ProfileWallMedia::PROFILE_TEACHER;
        $media->platform = $entity->platform->value;
        $media->platformLabel = $entity->platform->trans($this->translator);
        $media->url = $entity->url;
        $media->embedId = $entity->embedId;
        $media->title = $entity->title;
        $media->thumbnailUrl = $thumbnailUrl;

        return $media;
    }
}
