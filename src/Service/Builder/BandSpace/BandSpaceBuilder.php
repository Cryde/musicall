<?php declare(strict_types=1);

namespace App\Service\Builder\BandSpace;

use App\ApiResource\BandSpace\BandSpace as BandSpaceDTO;
use App\Entity\BandSpace\BandSpace as BandSpaceEntity;
use App\Entity\User;
use App\Enum\BandSpace\Role;

readonly class BandSpaceBuilder
{
    /**
     * @param BandSpaceEntity[] $entities
     * @return BandSpaceDTO[]
     */
    public function buildFromList(array $entities, User $user): array
    {
        return array_map(
            fn(BandSpaceEntity $entity): BandSpaceDTO => $this->buildItem($entity, $this->resolveRole($entity, $user)),
            $entities
        );
    }

    public function buildItem(BandSpaceEntity $entity, Role $role): BandSpaceDTO
    {
        $dto = new BandSpaceDTO();
        $dto->id = (string) $entity->id;
        $dto->name = $entity->name;
        $dto->role = $role->value;

        return $dto;
    }

    private function resolveRole(BandSpaceEntity $entity, User $user): Role
    {
        foreach ($entity->memberships as $membership) {
            if ($membership->user->id === $user->id) {
                return $membership->role;
            }
        }

        return Role::User;
    }
}
