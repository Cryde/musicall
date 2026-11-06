<?php

declare(strict_types=1);

namespace App\State\Provider\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Profile\ProfileWallItem;
use App\Entity\User;
use App\Security\Profile\ProfileWallAccess;
use App\Service\Builder\Profile\ProfileWallItemBuilder;
use App\Service\Profile\Wall\ProfileWallFeed;
use App\Service\Profile\Wall\WallCursor;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Translation\LocaleSwitcher;

/**
 * @implements ProviderInterface<ProfileWallItem>
 */
readonly class ProfileWallProvider implements ProviderInterface
{
    public function __construct(
        private ProfileWallAccess $wallAccess,
        private ProfileWallFeed $feed,
        private ProfileWallItemBuilder $itemBuilder,
        private LocaleSwitcher $localeSwitcher,
        private Security $security,
    ) {
    }

    /**
     * @return list<ProfileWallItem>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $owner = $this->wallAccess->owner((string) $uriVariables['username']);
        $viewer = $this->security->getUser();
        $viewer = $viewer instanceof User ? $viewer : null;
        if ($this->wallAccess->isHiddenFrom($owner, $viewer)) {
            return [];
        }

        // The operation's Assert\Regex has already refused a malformed cursor with a 422.
        $beforeValue = $context['filters']['before'] ?? null;
        $before = is_string($beforeValue) && $beforeValue !== '' ? WallCursor::parse($beforeValue) : null;
        $entries = $this->feed->page($owner, $this->localeSwitcher->getLocale(), $before, ProfileWallItem::PAGE_SIZE);

        return $this->itemBuilder->buildAll($entries, $viewer);
    }
}
