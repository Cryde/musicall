<?php declare(strict_types=1);

namespace App\Service\Builder\BandSpace;

use App\ApiResource\BandSpace\BandSpaceMember;
use App\Entity\BandSpace\BandSpaceMembership;

readonly class BandSpaceMemberBuilder
{
    public function buildItem(BandSpaceMembership $membership): BandSpaceMember
    {
        $dto = new BandSpaceMember();
        $dto->id = (string) $membership->id;
        $dto->bandSpaceId = (string) $membership->bandSpace->id;
        $dto->userId = (string) $membership->user->id;
        $dto->username = $membership->user->username;
        $dto->role = $membership->role->value;
        $dto->creationDatetime = $membership->creationDatetime->format(\DateTimeInterface::ATOM);

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
