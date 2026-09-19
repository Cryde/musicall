<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\Chat;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Chat\ChatMessageResource;
use App\Entity\Message\Message;
use App\Entity\User;
use App\Repository\Message\MessageRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\Builder\BandSpace\ChatMessageBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<mixed, ChatMessageResource>
 */
readonly class ChatMessageUnpinProcessor implements ProcessorInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private MessageRepository $messageRepository,
        private ChatMessageBuilder $chatMessageBuilder,
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ChatMessageResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        $bandSpaceId = (string) $uriVariables['bandSpaceId'];
        // Whoever can pin can unpin, including somebody else's pin: the bar is the band's, and a
        // door code that has changed has to be removable by whoever notices.
        [$bandSpace] = $this->memberChecker->checkMemberForWrite($bandSpaceId, $user);

        $message = $this->messageRepository->findOneByIdAndBandSpace((string) $uriVariables['id'], $bandSpace);
        if (!$message instanceof Message) {
            throw new NotFoundHttpException('Message introuvable');
        }

        // The pin is what this endpoint addresses, so a message that carries none has nothing here to
        // delete. A no-op 200 would tell a stale bar it succeeded in removing something that was
        // already gone.
        if ($message->pinnedDatetime === null) {
            throw new NotFoundHttpException('Ce message n\'est pas épinglé');
        }

        $message->pinnedDatetime = null;
        $message->pinnedBy = null;
        $this->entityManager->flush();

        return $this->chatMessageBuilder->buildItem($message, $bandSpaceId, $user);
    }
}
