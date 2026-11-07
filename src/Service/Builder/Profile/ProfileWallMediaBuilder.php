<?php

declare(strict_types=1);

namespace App\Service\Builder\Profile;

use App\ApiResource\Profile\ProfileWallMedia;
use App\Entity\Musician\MusicianProfileMedia;
use App\Entity\Teacher\TeacherProfileMedia;
use App\Service\Builder\Musician\MusicianProfileMediaResourceBuilder;
use App\Service\Builder\Teacher\TeacherProfileMediaResourceBuilder;
use Symfony\Contracts\Translation\TranslatorInterface;

/** A musician or teacher media as the profile plays it: a wall line (#1316) or « Écouter » (#1317). */
readonly class ProfileWallMediaBuilder
{
    public function __construct(
        private MusicianProfileMediaResourceBuilder $musicianMediaBuilder,
        private TeacherProfileMediaResourceBuilder $teacherMediaBuilder,
        private TranslatorInterface $translator,
    ) {
    }

    public function build(MusicianProfileMedia|TeacherProfileMedia $entity): ProfileWallMedia
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

    /**
     * @param iterable<MusicianProfileMedia|TeacherProfileMedia> $entities
     *
     * @return list<ProfileWallMedia>
     */
    public function buildList(iterable $entities): array
    {
        $list = [];
        foreach ($entities as $entity) {
            $list[] = $this->build($entity);
        }

        return $list;
    }
}
