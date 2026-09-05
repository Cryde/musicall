<?php declare(strict_types=1);

namespace App\Service\Builder\BandSpace;

use App\ApiResource\BandSpace\MemberAbsenceResource;
use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\BandSpace\MemberAbsence;
use App\Security\BandSpace\MemberAbsenceChecker;
use App\Service\Builder\User\UserProfilePictureUrlBuilder;

readonly class MemberAbsenceBuilder
{
    public function __construct(
        private MemberAbsenceChecker $memberAbsenceChecker,
        private UserProfilePictureUrlBuilder $profilePictureUrlBuilder,
    ) {
    }

    /**
     * @param MemberAbsence[] $entities
     * @return MemberAbsenceResource[]
     */
    public function buildFromList(array $entities, BandSpaceMembership $viewer): array
    {
        return array_map(
            fn(MemberAbsence $entity): MemberAbsenceResource => $this->buildItem($entity, $viewer),
            $entities
        );
    }

    public function buildItem(MemberAbsence $entity, BandSpaceMembership $viewer): MemberAbsenceResource
    {
        $dto = new MemberAbsenceResource();
        $dto->id = (string) $entity->id;
        $dto->bandSpaceId = (string) $entity->member->bandSpace->id;
        $dto->memberId = (string) $entity->member->id;
        $dto->displayName = $entity->member->displayName();
        $dto->profilePictureUrl = $this->profilePictureUrlBuilder->build($entity->member->user);
        // The column is a DATE and the DTO a `Y-m-d` string, so the day goes out as written with no
        // offset for a reader to unpin. That bug class is what assets/js/utils/agendaDate.js documents.
        $dto->startDate = $entity->startDate->format('Y-m-d');
        $dto->endDate = $entity->endDate->format('Y-m-d');
        $dto->reason = $entity->reason;
        $dto->canManage = $this->memberAbsenceChecker->canManage($entity->member, $viewer);
        $dto->creationDatetime = $entity->creationDatetime;

        return $dto;
    }
}
