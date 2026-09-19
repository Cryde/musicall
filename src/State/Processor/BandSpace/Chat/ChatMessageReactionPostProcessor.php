<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\Chat;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Chat\ChatMessageReaction;
use App\ApiResource\BandSpace\Chat\ChatMessageResource;
use App\Entity\Message\Message;
use App\Entity\User;
use App\Enum\Message\MessageReactionEmoji;
use App\Repository\Message\MessageReactionRepository;
use App\Repository\Message\MessageRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\Builder\BandSpace\ChatMessageBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Leaves one reaction on one message (#968).
 *
 * Idempotent: reacting with something already held changes nothing and answers with the message as it
 * stands. That is the whole reason this is not a toggle, so a double tap or a retry cannot silently
 * undo what the first tap did.
 *
 * No notification and no activity feed entry, deliberately. Concern 10 of #948: a thumbs up is the
 * cheap acknowledgement that replaces five « ok » messages, and it stops being cheap the moment it
 * rings somebody's bell.
 *
 * @implements ProcessorInterface<ChatMessageReaction, ChatMessageResource>
 */
readonly class ChatMessageReactionPostProcessor implements ProcessorInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private MessageRepository $messageRepository,
        private MessageReactionRepository $messageReactionRepository,
        private ChatMessageBuilder $chatMessageBuilder,
        private Security $security,
    ) {
    }

    /**
     * @param ChatMessageReaction $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ChatMessageResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        $bandSpaceId = (string) $uriVariables['bandSpaceId'];
        [$bandSpace] = $this->memberChecker->checkMemberForWrite($bandSpaceId, $user);

        $message = $this->messageRepository->findOneByIdAndBandSpace((string) $uriVariables['id'], $bandSpace);
        if (!$message instanceof Message) {
            throw new NotFoundHttpException('Message introuvable');
        }

        // from(), not tryFrom(): Assert\Choice on the input has already refused anything else with a
        // 422 naming the field.
        $this->messageReactionRepository->add($message, $user, MessageReactionEmoji::from($data->emoji));

        return $this->chatMessageBuilder->buildItem($message, $bandSpaceId, $user);
    }
}
