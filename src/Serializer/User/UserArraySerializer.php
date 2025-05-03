<?php

namespace App\Serializer\User;

use App\Entity\User;

class UserArraySerializer
{
    public function __construct(private readonly UserProfilePictureArraySerializer $userProfilePictureArraySerializer)
    {
    }

    public function toArray(User $user, bool $self = false): array
    {
        $profilePicture = $user->getProfilePicture();

        $data = [
            'id'       => $user->getId(),
            'username' => $user->getUsername(),
            'picture'  => $profilePicture ? $this->userProfilePictureArraySerializer->toArray($profilePicture) : null,
        ];

        if ($self) {
            $data['email'] = $user->getEmail();
        }

        return $data;
    }
}
