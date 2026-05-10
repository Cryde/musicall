<?php declare(strict_types=1);

namespace App\State\Processor\Message;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Message\MessageResource;
use App\Entity\User;
use App\Service\Access\ThreadAccess;
use App\Service\Builder\Message\MessageBuilder;
use App\Service\Procedure\Message\MessageSenderProcedure;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @implements ProcessorInterface<MessageResource, MessageResource>
 */
class MessagePostProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly Security               $security,
        private readonly ThreadAccess           $threadAccess,
        private readonly MessageSenderProcedure $messageSenderProcedure,
        private readonly MessageBuilder         $messageBuilder,
        #[Target('message_send')]
        private readonly RateLimiterFactoryInterface $messageSendLimiter,
    ) {
    }

    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): MessageResource
    {
        if (!$this->security->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            throw new AccessDeniedException('Vous n\'êtes pas connecté.');
        }
        /** @var User $user */
        $user = $this->security->getUser();
        $this->messageSendLimiter->create($user->getUserIdentifier())->consume()->ensureAccepted();

        if (!$this->threadAccess->isOneOfParticipant($data->thread, $user)) {
            throw new AccessDeniedException('Vous n\'êtes pas autorisé à voir ceci.');
        }

        $message = $this->messageSenderProcedure->processByThread($data->thread, $user, $data->content);

        return $this->messageBuilder->buildItem($message);
    }
}
