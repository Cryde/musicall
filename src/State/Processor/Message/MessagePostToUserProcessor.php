<?php declare(strict_types=1);

namespace App\State\Processor\Message;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Message\MessageResource;
use App\ApiResource\Message\MessageUser;
use App\Entity\User;
use App\Service\Builder\Message\MessageBuilder;
use App\Service\Procedure\Message\MessageSenderProcedure;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @implements ProcessorInterface<MessageUser, MessageResource>
 */
class MessagePostToUserProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly Security               $security,
        private readonly MessageSenderProcedure $messageSenderProcedure,
        private readonly MessageBuilder         $messageBuilder,
        #[Target('thread_creation')]
        private readonly RateLimiterFactoryInterface $threadCreationLimiter,
        #[Target('message_send')]
        private readonly RateLimiterFactoryInterface $messageSendLimiter,
    ) {
    }

    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): MessageResource
    {
        /** @var MessageUser $data */
        if (!$this->security->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            throw new AccessDeniedException('Vous n\'êtes pas connecté.');
        }
        /** @var User $currentUser */
        $currentUser = $this->security->getUser();
        $userIdentifier = $currentUser->getUserIdentifier();
        $this->threadCreationLimiter->create($userIdentifier)->consume()->ensureAccepted();
        $this->messageSendLimiter->create($userIdentifier)->consume()->ensureAccepted();

        $message = $this->messageSenderProcedure->process($currentUser, $data->recipient, $data->content);

        return $this->messageBuilder->buildItem($message);
    }
}
