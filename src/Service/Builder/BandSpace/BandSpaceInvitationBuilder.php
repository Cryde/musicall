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
        // Only what the inviter typed: an invitation by username never discloses the account's email.
        $dto->email = $invitation->invitedByUsername ? null : $invitation->email;
        $dto->invitedUsername = $invitation->invitedByUsername ? $invitation->existingUser?->username : null;
        $dto->status = $invitation->status->value;
        $dto->creationDatetime = $invitation->creationDatetime;
        $dto->expirationDatetime = $invitation->expirationDatetime;

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
