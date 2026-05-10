<?php declare(strict_types=1);

namespace App\Service\Builder\BandSpace;

use App\ApiResource\BandSpace\BandSpaceMember;
use App\Entity\BandSpace\BandSpaceMembership;
use App\Service\Builder\User\UserProfilePictureUrlBuilder;

readonly class BandSpaceMemberBuilder
{
    public function __construct(
        private UserProfilePictureUrlBuilder $profilePictureUrlBuilder,
    ) {
    }

    public function buildItem(BandSpaceMembership $membership): BandSpaceMember
    {
        $dto = new BandSpaceMember();
        $dto->id = (string) $membership->id;
        $dto->bandSpaceId = (string) $membership->bandSpace->id;
        $dto->userId = $membership->user->id;
        $dto->username = $membership->user->username;
        $dto->role = $membership->role->value;
        $dto->profilePictureUrl = $this->profilePictureUrlBuilder->build($membership->user);
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
}
