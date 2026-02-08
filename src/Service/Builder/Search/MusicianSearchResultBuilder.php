<?php declare(strict_types=1);

namespace App\Service\Builder\Search;

use App\ApiResource\Search\AnnounceMusician;
use App\ApiResource\Search\Result\Instrument;
use App\ApiResource\Search\Result\Style;
use App\ApiResource\Search\Result\User;
use App\Entity\Attribute\Instrument as InstrumentEntity;
use App\Entity\Attribute\Style as StyleEntity;
use App\Entity\Musician\MusicianAnnounce;
use App\Entity\User as UserEntity;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class MusicianSearchResultBuilder
{
    public function __construct(
        private readonly HtmlSanitizerInterface $appOnlybrSanitizer,
        private readonly UploaderHelper         $uploaderHelper,
        private readonly CacheManager           $cacheManager
    ) {
    }

    /**
     * @param array<int, MusicianAnnounce|array{0: MusicianAnnounce, distance: float}> $list
     *
     * @return AnnounceMusician[]
     */
    public function buildFromList(array $list): array
    {
        $result = [];
        foreach ($list as $item) {
            $result[] = is_array($item) ? $this->build($item[0], $item['distance']) : $this->build($item);
        }

        return $result;
    }

    public function build(MusicianAnnounce $musicianAnnounce, ?float $distance = null): AnnounceMusician
    {
        $instrument = $musicianAnnounce->getInstrument();

        $announceMusician = new AnnounceMusician();
        $announceMusician->id = (string) $musicianAnnounce->getId();
        $announceMusician->user = $this->buildUser($musicianAnnounce->getAuthor());
        $announceMusician->instrument = $this->buildInstrument($instrument);
        $announceMusician->styles = $this->buildStyles($musicianAnnounce->getStyles()->toArray());
        $announceMusician->note = $this->appOnlybrSanitizer->sanitize((string) $musicianAnnounce->getNote());
        $announceMusician->locationName = $musicianAnnounce->getLocationName();
        $announceMusician->type = (int) $musicianAnnounce->getType();
        if ($distance !== null) {
            $announceMusician->distance = $distance / 1000;
        }

        return $announceMusician;
    }

    private function buildUser(UserEntity $userEntity): User
    {
        $user = new User();
        $user->id = (string) $userEntity->getId();
        $user->username = $userEntity->getUsername();
        $user->deletionDatetime = $userEntity->getDeletionDatetime();
        $user->hasMusicianProfile = $userEntity->getMusicianProfile() !== null;
        if ($userEntity->getProfilePicture()) {
            $path = $this->uploaderHelper->asset($userEntity->getProfilePicture(), 'imageFile');
            if ($path !== null) {
                $user->profilePictureUrl = $this->cacheManager->getBrowserPath($path, 'user_profile_picture_small');
            }
        }

        return $user;
    }

    private function buildInstrument(InstrumentEntity $instrumentEntity): Instrument
    {
        $instrument = new Instrument();
        $instrument->name = (string) $instrumentEntity->getMusicianName();

        return $instrument;
    }

    /**
     * @param StyleEntity[] $styles
     *
     * @return Style[]
     */
    private function buildStyles(array $styles): array
    {
        return array_map(static function (StyleEntity $styleEntity): Style {
            $style = new Style();
            $style->name = (string) $styleEntity->getName();

            return $style;
        }, $styles);
    }
}
