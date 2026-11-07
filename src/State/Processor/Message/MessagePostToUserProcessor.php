<?php declare(strict_types=1);

namespace App\State\Processor\Message;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Message\MessageResource;
use App\ApiResource\Message\MessageUser;
use App\Entity\Musician\MusicianAnnounce;
use App\Entity\Teacher\TeacherProfile;
use App\Entity\User;
use App\Enum\Message\LessonRequest;
use App\Repository\Message\MessageContactOriginRepository;
use App\Service\Builder\Message\MessageBuilder;
use App\Service\Message\ContactOriginResolver;
use App\Service\Message\TeacherProfileContact;
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
            throw new AccessDeniedException('common.not_authenticated');
        }
        /** @var User $currentUser */
        $currentUser = $this->security->getUser();
        $userIdentifier = $currentUser->getUserIdentifier();
        $this->threadCreationLimiter->create($userIdentifier)->consume()->ensureAccepted();
        $this->messageSendLimiter->create($userIdentifier)->consume()->ensureAccepted();

        if (!$this->contactPolicy->canContact($currentUser, $data->recipient)) {
            throw new AccessDeniedHttpException(UserContactPolicy::BLOCKED_MESSAGE);
        }

        $message = $this->messageSenderProcedure->process($currentUser, $data->recipient, $data->content, $this->contactOrigin($data));

        return $this->messageBuilder->buildItem(
            $message,
            contactOrigin: $this->messageContactOriginRepository->findByMessageIds([(string) $message->id])[(string) $message->id] ?? null,
            viewer: $currentUser,
        );
    }

    /** ValidContactOrigin has already checked every id and value against the recipient. */
    private function contactOrigin(MessageUser $data): MusicianAnnounce|TeacherProfileContact|null
    {
        if ($data->musicianAnnounceId !== null) {
            return $this->contactOriginResolver->musicianAnnounceOf($data->musicianAnnounceId, $data->recipient);
        }
        $profile = $data->fromTeacherProfile ? $this->contactOriginResolver->teacherProfileOf($data->recipient) : null;
        if (!$profile instanceof TeacherProfile) {
            return null;
        }

        return new TeacherProfileContact(
            $profile,
            $data->teacherInstrumentId !== null ? $this->contactOriginResolver->taughtInstrumentOf($profile, $data->teacherInstrumentId) : null,
            $data->lessonRequest !== null ? LessonRequest::tryFrom($data->lessonRequest) : null,
        );
    }
}
