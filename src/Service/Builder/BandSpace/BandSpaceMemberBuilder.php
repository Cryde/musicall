<?php declare(strict_types=1);

namespace App\Service\Builder\BandSpace;

use App\ApiResource\BandSpace\BandSpaceMember;
use App\Entity\BandSpace\BandSpaceMembership;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

readonly class BandSpaceMemberBuilder
{
    public function __construct(
        private UploaderHelper $uploaderHelper,
        private CacheManager $cacheManager,
    ) {
    }

    public function buildItem(BandSpaceMembership $membership): BandSpaceMember
    {
        $dto = new BandSpaceMember();
        $dto->id = (string) $membership->id;
        $dto->bandSpaceId = (string) $membership->bandSpace->id;
        $dto->userId = (string) $membership->user->id;
        $dto->username = $membership->user->username;
        $dto->role = $membership->role->value;
        $dto->profilePictureUrl = $this->buildProfilePictureUrl($membership);
        $dto->creationDatetime = $membership->creationDatetime->format(\DateTimeInterface::ATOM);
        $dto->status = $membership->status->value;
        $dto->leftDatetime = $membership->leftDatetime?->format(\DateTimeInterface::ATOM);

        return $dto;
    }

    /**
     * @param BandSpaceMembership[] $memberships
     * @return BandSpaceMember[]
     */
    public function buildList(array $memberships): array
    {
        return array_map(
            fn(BandSpaceMembership $m): BandSpaceMember => $this->buildItem($m),
            $memberships
        );
    }

    private function buildProfilePictureUrl(BandSpaceMembership $membership): ?string
    {
        $profilePicture = $membership->user->profilePicture;
        if (!$profilePicture) {
            return null;
        }

        $path = $this->uploaderHelper->asset($profilePicture, 'imageFile');
        if (!$path) {
            return null;
        }

        return $this->cacheManager->getBrowserPath($path, 'user_profile_picture_small');
    }
}
