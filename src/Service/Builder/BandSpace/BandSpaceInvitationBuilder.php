<?php declare(strict_types=1);

namespace App\Service\Builder\BandSpace;

use App\ApiResource\BandSpace\Invitation\BandSpaceInvitationResource;
use App\Entity\BandSpace\BandSpaceInvitation;

readonly class BandSpaceInvitationBuilder
{
    public function buildItem(BandSpaceInvitation $invitation): BandSpaceInvitationResource
    {
        $dto = new BandSpaceInvitationResource();
        $dto->id = (string) $invitation->id;
        $dto->bandSpaceId = (string) $invitation->bandSpace->id;
        $dto->email = $invitation->email;
        $dto->status = $invitation->status->value;
        $dto->creationDatetime = $invitation->creationDatetime->format(\DateTimeInterface::ATOM);
        $dto->expirationDatetime = $invitation->expirationDatetime->format(\DateTimeInterface::ATOM);

        return $dto;
    }

    /**
     * @param BandSpaceInvitation[] $invitations
     * @return BandSpaceInvitationResource[]
     */
    public function buildList(array $invitations): array
    {
        return array_map(
            fn(BandSpaceInvitation $i): BandSpaceInvitationResource => $this->buildItem($i),
            $invitations
        );
    }
}
