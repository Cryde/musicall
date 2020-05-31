<?php

namespace App\Serializer\User;

use App\Entity\User;

class UserSearchArraySerializer
{
    /**
     * @param User[] $users
     *
     * @return array
     */
    public function listToArray($users): array
    {
        $result = [];
        foreach ($users as $user) {
            $result[] = $this->toArray($user);
        }

        return $result;
    }

    public function toArray(User $user): array
    {
        return [
            'id'       => $user->getId(),
            'username' => $user->getUsername(),
        ];
    }
}
