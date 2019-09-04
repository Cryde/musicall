<?php

namespace App\Serializer;

use App\Entity\User;

class UserAppArraySerializer
{
    public function toArray(User $user): array
    {
        return [
            'username' => $user->getUsername(),
        ];
    }
}
