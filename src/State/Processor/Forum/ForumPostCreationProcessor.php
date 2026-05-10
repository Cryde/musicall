<?php

declare(strict_types=1);

namespace App\State\Processor\Forum;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Forum\ForumPostCreation;
use App\ApiResource\Forum\ForumPostResource;
use App\Entity\User;
use App\Service\Procedure\Forum\MessageCreationProcedure;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * @implements ProcessorInterface<ForumPostCreation, ForumPostResource>
 */
readonly class ForumPostCreationProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageCreationProcedure $messageCreationProcedure,
        private Security $security,
        #[Target('forum_reply')]
        private RateLimiterFactoryInterface $forumReplyLimiter,
    ) {
    }

    /**
     * @param ForumPostCreation $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ForumPostResource
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $this->forumReplyLimiter->create($user->getUserIdentifier())->consume()->ensureAccepted();

        return $this->messageCreationProcedure->process($data->topic, $data->content);
    }
}
