<?php

declare(strict_types=1);

namespace App\State\Processor\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Profile\ProfilePostCreate;
use App\ApiResource\Profile\ProfilePostResource;
use App\Entity\User;
use App\Service\Builder\Profile\ProfilePostResourceBuilder;
use App\Service\Procedure\Profile\CreateProfilePostProcedure;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * @implements ProcessorInterface<ProfilePostCreate, ProfilePostResource>
 */
readonly class ProfilePostCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private CreateProfilePostProcedure $createProcedure,
        private ProfilePostResourceBuilder $postBuilder,
        #[Target('profile_post')]
        private RateLimiterFactoryInterface $profilePostLimiter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ProfilePostResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }
        $this->profilePostLimiter->create($user->getUserIdentifier())->consume()->ensureAccepted();

        return $this->postBuilder->build($this->createProcedure->process($user, $data), $user);
    }
}
