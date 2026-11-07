<?php

declare(strict_types=1);

namespace App\Service\Builder\User;

use App\ApiResource\User\Profile\PublicProfile;
use App\ApiResource\User\Profile\PublicProfileMusician;
use App\ApiResource\User\Profile\PublicProfileSocialLink;
use App\ApiResource\User\Profile\PublicProfileTeacher;
use App\Entity\Musician\MusicianProfile;
use App\Entity\Musician\MusicianProfileInstrument;
use App\Entity\Teacher\TeacherProfile;
use App\Entity\Teacher\TeacherProfilePricing;
use App\Entity\User;
use App\Entity\User\UserProfile;
use App\Entity\User\UserSocialLink;
use App\Enum\Musician\AvailabilityStatus;
use App\Repository\Musician\MusicianAnnounceRepository;
use App\Service\Builder\Musician\MusicianBandResourceBuilder;
use App\Service\Builder\Musician\MusicianInstrumentResourceBuilder;
use App\Service\Builder\Musician\MusicianProfileBuilder;
use App\Service\Builder\Profile\ProfileWallMediaBuilder;
use App\Service\Builder\Teacher\TeacherProfileBuilder;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Contracts\Translation\TranslatorInterface;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

readonly class PublicProfileBuilder
{
    public function __construct(
        private UploaderHelper $uploaderHelper,
        private CacheManager $cacheManager,
        private MusicianAnnounceRepository $musicianAnnounceRepository,
        private TranslatorInterface $translator,
        private PublicProfileAnnounceBuilder $announceBuilder,
        private MusicianInstrumentResourceBuilder $musicianInstrumentBuilder,
        private MusicianProfileBuilder $musicianProfileBuilder,
        private TeacherProfileBuilder $teacherProfileBuilder,
        private ProfileWallMediaBuilder $mediaBuilder,
        private MusicianBandResourceBuilder $bandBuilder,
    ) {
    }

    public function build(User $user): PublicProfile
    {
        $profile = $user->profile;
        $memberSince = $user->creationDatetime;

        $dto = new PublicProfile();

        $dto->username = $user->username;
        $dto->userId = $user->id;
        $dto->displayName = $profile->displayName;
        $dto->bio = $profile->bio;
        $dto->location = $profile->location;
        $dto->memberSince = $memberSince;

        // Profile picture
        if ($user->profilePicture instanceof \App\Entity\Image\UserProfilePicture) {
            $path = $this->uploaderHelper->asset($user->profilePicture, 'imageFile');
            if ($path !== null) {
                $dto->profilePictureUrl = $this->cacheManager->getBrowserPath($path, 'user_profile_picture_small');
                $dto->profilePictureLargeUrl = $this->cacheManager->getBrowserPath($path, 'user_profile_picture_large');
            }
        }

        // Cover picture
        if ($profile->coverPicture instanceof \App\Entity\Image\UserProfileCoverPicture) {
            $path = $this->uploaderHelper->asset($profile->coverPicture, 'imageFile');
            if ($path !== null) {
                $dto->coverPictureUrl = $this->cacheManager->getBrowserPath($path, 'user_cover_picture');
            }
        }

        // Social links
        $dto->socialLinks = $this->buildSocialLinks($profile->socialLinks->toArray());

        // Musician announces
        $announces = $this->musicianAnnounceRepository->findBy(['author' => $user], ['creationDatetime' => 'DESC']);
        $dto->musicianAnnounces = array_map($this->announceBuilder->build(...), $announces);

        $dto->hasMusicianProfile = $user->musicianProfile instanceof MusicianProfile;
        $dto->hasTeacherProfile = $user->teacherProfile instanceof TeacherProfile;
        $dto->musician = $user->musicianProfile instanceof MusicianProfile ? $this->musician($user->musicianProfile) : null;
        $dto->teacher = $user->teacherProfile instanceof TeacherProfile ? $this->teacher($user->teacherProfile) : null;

        return $dto;
    }

    private function musician(MusicianProfile $profile): PublicProfileMusician
    {
        $musician = new PublicProfileMusician();
        if ($profile->availabilityStatus instanceof AvailabilityStatus) {
            $musician->availabilityStatus = $profile->availabilityStatus->value;
            $musician->availabilityStatusLabel = $profile->availabilityStatus->trans($this->translator);
        }
        // Their strongest instrument first: « Ce que je fais » names it. The relation itself has no order.
        $instruments = $profile->instruments->toArray();
        usort($instruments, static fn (MusicianProfileInstrument $a, MusicianProfileInstrument $b): int => $b->skillLevel->getSortOrder() <=> $a->skillLevel->getSortOrder()
            ?: strcmp((string) $a->instrument->slug, (string) $b->instrument->slug));
        $musician->instruments = $this->musicianInstrumentBuilder->buildList($instruments);
        $musician->styles = $this->musicianProfileBuilder->buildStyles($profile->styles->toArray());
        $musician->media = $this->mediaBuilder->buildList($profile->media);
        $musician->bands = $this->bandBuilder->buildList($profile->bands);

        return $musician;
    }

    private function teacher(TeacherProfile $profile): PublicProfileTeacher
    {
        $teacher = new PublicProfileTeacher();
        $teacher->instruments = $this->teacherProfileBuilder->buildInstruments($profile->instruments->toArray());
        $teacher->offersTrial = $profile->offersTrial;
        $teacher->trialPrice = $profile->trialPrice;
        // Shortest lesson first: the relation itself has no order.
        $pricing = $profile->pricing->toArray();
        usort($pricing, static fn (TeacherProfilePricing $a, TeacherProfilePricing $b): int => $a->duration->getMinutes() <=> $b->duration->getMinutes());
        $teacher->pricing = $this->teacherProfileBuilder->buildPricing($pricing);
        $teacher->media = $this->mediaBuilder->buildList($profile->media);

        return $teacher;
    }

    /**
     * @param UserSocialLink[] $links
     * @return PublicProfileSocialLink[]
     */
    private function buildSocialLinks(array $links): array
    {
        return array_map(function (UserSocialLink $link): PublicProfileSocialLink {
            $dto = new PublicProfileSocialLink();
            $dto->platform = $link->platform->value;
            $dto->platformLabel = $link->platform->trans($this->translator);
            $dto->url = $link->url;

            return $dto;
        }, $links);
    }
}
