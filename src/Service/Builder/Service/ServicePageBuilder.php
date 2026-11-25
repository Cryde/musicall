<?php

declare(strict_types=1);

namespace App\Service\Builder\Service;

use App\ApiResource\Service\ServicePage;
use App\Entity\Musician\MusicianProfile;
use App\Entity\Service\Service;
use App\Service\Builder\Musician\MusicianInstrumentResourceBuilder;
use App\Service\Builder\Teacher\TeacherProfileBuilder;
use App\Service\Builder\Teacher\TeacherProfileMediaResourceBuilder;
use App\Service\Builder\User\UserProfilePictureUrlBuilder;

/** What every kind's page shows (#1410, #1411): each kind's builder adds its details. */
readonly class ServicePageBuilder
{
    public function __construct(
        private ServiceViewBuilder $serviceViewBuilder,
        private UserProfilePictureUrlBuilder $pictureUrlBuilder,
        private MusicianInstrumentResourceBuilder $musicianInstrumentResourceBuilder,
        private TeacherProfileBuilder $teacherProfileBuilder,
        private TeacherProfileMediaResourceBuilder $mediaResourceBuilder,
    ) {
    }

    public function fill(ServicePage $page, Service $service): void
    {
        $user = $service->user;
        $page->username = $user->username;
        $page->id = (string) $service->id;
        $page->userId = (string) $user->id;
        $page->displayName = $user->getDisplayName();
        $page->memberSince = $user->creationDatetime;
        $page->isPublic = $user->profile->isPublic;
        $page->status = $service->status->value;
        $page->profilePictureUrl = $this->pictureUrlBuilder->build($user);
        $page->bio = $user->profile->bio;
        $page->musicianInstruments = $user->musicianProfile instanceof MusicianProfile
            ? $this->musicianInstrumentResourceBuilder->buildList($user->musicianProfile->instruments)
            : [];

        $page->headline = $service->headline;
        $page->description = $service->description;
        $page->yearsOfExperience = $service->yearsOfExperience;
        $page->specialities = $service->specialities;
        $page->styles = $this->teacherProfileBuilder->buildStyles($service->styles->toArray());
        $page->places = $this->serviceViewBuilder->places($service);
        $page->offers = $this->serviceViewBuilder->offers($service);
        $page->media = $this->mediaResourceBuilder->buildList($service->media->toArray());
        $page->works = $this->serviceViewBuilder->works($service);
    }
}
