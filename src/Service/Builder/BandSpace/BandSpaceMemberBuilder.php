<?php declare(strict_types=1);

namespace App\Service\Builder\BandSpace;

use App\ApiResource\BandSpace\BandSpaceMember;
use App\Entity\BandSpace\BandSpaceMembership;
use App\Service\Builder\User\UserProfilePictureUrlBuilder;
use App\Translation\DisplayNameTranslator;
use App\Translation\ReferenceNameSorter;

readonly class BandSpaceMemberBuilder
{
    public function __construct(
        private UserProfilePictureUrlBuilder $profilePictureUrlBuilder,
        private ReferenceNameSorter $referenceNameSorter,
        private DisplayNameTranslator $displayNames,
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
        $dto->stageName = $membership->stageName;
        $dto->showEmailOnRiders = $membership->showEmailOnRiders;
        // The name a document should use, resolved here so no caller has to repeat the fallback.
        $dto->displayName = $this->displayNames->memberName($membership);
        $dto->isDeleted = $membership->user->isDeleted();
        $dto->instruments = $this->referenceNameSorter->instrumentNames($membership->instruments);
        $dto->profilePictureUrl = $this->profilePictureUrlBuilder->build($membership->user);
        $dto->creationDatetime = $membership->creationDatetime;
        $dto->status = $membership->status->value;
        $dto->leftDatetime = $membership->leftDatetime;

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
