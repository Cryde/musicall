<?php declare(strict_types=1);

namespace App\Service\Builder\User\Block;

use App\ApiResource\User\Block\UserBlockResource;
use App\Entity\User\Relation\UserBlock;
use App\Service\Builder\User\UserProfilePictureUrlBuilder;

readonly class UserBlockBuilder
{
    public function __construct(
        private UserProfilePictureUrlBuilder $profilePictureUrlBuilder,
    ) {
    }

    public function build(UserBlock $block): UserBlockResource
    {
        $blocked = $block->blocked;

        $resource = new UserBlockResource();
        $resource->userId = (string) $blocked->id;
        $resource->username = $blocked->username;
        $resource->displayName = $blocked->publicName();
        $resource->profilePictureUrl = $this->profilePictureUrlBuilder->build($blocked);
        $resource->creationDatetime = $block->creationDatetime;

        return $resource;
    }

    /**
     * @param list<UserBlock> $blocks
     *
     * @return list<UserBlockResource>
     */
    public function buildList(array $blocks): array
    {
        return array_map($this->build(...), $blocks);
    }
}
