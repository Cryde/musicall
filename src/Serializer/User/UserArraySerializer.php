<?php

namespace App\Serializer\User;

use App\Entity\User;

class UserArraySerializer
{
    public function toArray(User $user, bool $self = false): array
    {
        $data = [
            'id'              => $user->getId(),
            'username'        => $user->getUsername(),
            'profile_picture' => '',
        ];

        if ($self) {
            $data['email'] = $user->getEmail();
        }

        return $data;
    }
}
