<?php

namespace App\Serializer\User;

use App\Entity\User;

class UserArraySerializer
{
    private UserProfilePictureArraySerializer $userProfilePictureArraySerializer;

    public function __construct(UserProfilePictureArraySerializer $userProfilePictureArraySerializer)
    {
        $this->userProfilePictureArraySerializer = $userProfilePictureArraySerializer;
    }

    /**
     * @param User[] $list
     *
     * @return array
     */
    public function listToArray($list)
    {
        $result = [];
        foreach ($list as $user) {
            $result[] = $this->toArray($user);
        }

        return $result;
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
