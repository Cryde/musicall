<?php

declare(strict_types=1);

namespace App\State\Provider\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Profile\ProfilePostCommentResource;
use App\Entity\User;
use App\Security\Profile\ProfileWallAccess;
use App\Service\Builder\Profile\ProfilePostCommentResourceBuilder;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * One comment, read as its post's comment list would show it.
 *
 * @implements ProviderInterface<ProfilePostCommentResource>
 */
readonly class ProfilePostCommentProvider implements ProviderInterface
{
    public function __construct(
        private ProfileWallAccess $wallAccess,
        private ProfilePostCommentResourceBuilder $commentBuilder,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ProfilePostCommentResource
    {
        $viewer = $this->security->getUser();
        $viewer = $viewer instanceof User ? $viewer : null;

        return $this->commentBuilder->build($this->wallAccess->visibleComment((string) $uriVariables['id'], $viewer), $viewer);
    }
}
