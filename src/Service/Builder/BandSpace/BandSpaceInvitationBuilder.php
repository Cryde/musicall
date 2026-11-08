<?php declare(strict_types=1);

namespace App\Service\Builder\BandSpace;

use App\ApiResource\BandSpace\Invitation\BandSpaceInvitationResource;
use App\ApiResource\BandSpace\Invitation\UserBandSpaceInvitation;
use App\Entity\BandSpace\BandSpaceInvitation;
use App\Translation\DisplayNameTranslator;

readonly class BandSpaceInvitationBuilder
{
    public function __construct(
        private DisplayNameTranslator $displayNames,
    ) {
    }

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

    /**
     * @param list<BandSpaceInvitation> $invitations
     * @return list<UserBandSpaceInvitation>
     */
    public function buildReceivedList(array $invitations): array
    {
        return array_map(
            function (BandSpaceInvitation $invitation): UserBandSpaceInvitation {
                $dto = new UserBandSpaceInvitation();
                $dto->id = (string) $invitation->id;
                $dto->token = $invitation->token;
                $dto->bandSpaceId = (string) $invitation->bandSpace->id;
                $dto->bandSpaceName = $invitation->bandSpace->name;
                // A closed account is anonymised in place, so its username reads "deleted_<uuid>".
                $dto->invitedByUsername = $invitation->invitedBy->isDeleted()
                    ? $this->displayNames->deletedUser()
                    : $invitation->invitedBy->username;
                $dto->creationDatetime = $invitation->creationDatetime;

                return $dto;
            },
            $invitations
        );
    }
}
