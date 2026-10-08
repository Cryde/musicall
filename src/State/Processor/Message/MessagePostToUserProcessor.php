<?php declare(strict_types=1);

namespace App\State\Processor\Message;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Message\MessageResource;
use App\ApiResource\Message\MessageUser;
use App\Entity\User;
use App\Repository\Message\MessageContactOriginRepository;
use App\Service\Builder\Message\MessageBuilder;
use App\Service\Message\ContactOriginResolver;
use App\Service\Procedure\Message\MessageSenderProcedure;
use App\Service\User\Relation\UserContactPolicy;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
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
        private readonly UserContactPolicy      $contactPolicy,
        private readonly ContactOriginResolver  $contactOriginResolver,
        private readonly MessageContactOriginRepository $messageContactOriginRepository,
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

        if (!$this->contactPolicy->canContact($currentUser, $data->recipient)) {
            throw new AccessDeniedHttpException(UserContactPolicy::BLOCKED_MESSAGE);
        }

        // ValidContactOrigin has already checked both ids against the recipient.
        $contactOrigin = $data->musicianAnnounceId !== null
            ? $this->contactOriginResolver->musicianAnnounceOf($data->musicianAnnounceId, $data->recipient)
            : ($data->fromTeacherProfile ? $this->contactOriginResolver->teacherProfileOf($data->recipient) : null);

        $message = $this->messageSenderProcedure->process($currentUser, $data->recipient, $data->content, $contactOrigin);

        return $this->messageBuilder->buildItem(
            $message,
            contactOrigin: $this->messageContactOriginRepository->findByMessageIds([(string) $message->id])[(string) $message->id] ?? null,
        );
    }
}
