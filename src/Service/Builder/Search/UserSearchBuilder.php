<?php declare(strict_types=1);

namespace App\Service\Builder\Search;

use App\ApiResource\Search\UserSearch;
use App\Entity\User;

class UserSearchBuilder
{
    /**
     * @param User[] $users
     *
     * @return UserSearch[]
     */
    public function buildList(array $users): array
    {
        return array_map(fn(User $user): UserSearch => $this->build($user), $users);
    }

    public function build(User $user): UserSearch
    {
        $userSearch = new UserSearch();
        $userSearch->id = (string) $user->getId();
        $userSearch->username = $user->getUsername();

        return $userSearch;
    }
}
