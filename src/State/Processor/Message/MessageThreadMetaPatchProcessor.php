<?php declare(strict_types=1);

namespace App\State\Processor\Message;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Message\MessageThreadMetaResource;
use App\Entity\Message\MessageThreadMeta;
use App\Entity\User;
use App\Repository\Message\MessageThreadMetaRepository;
use App\Service\Builder\Message\MessageThreadMetaBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @implements ProcessorInterface<MessageThreadMetaResource, MessageThreadMetaResource>
 */
readonly class MessageThreadMetaPatchProcessor implements ProcessorInterface
{
    public function __construct(
        private Security                    $security,
        private EntityManagerInterface      $entityManager,
        private MessageThreadMetaRepository $messageThreadMetaRepository,
        private MessageThreadMetaBuilder    $messageThreadMetaBuilder,
    ) {
    }

    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): MessageThreadMetaResource
    {
        if (!$this->security->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            throw new AccessDeniedException('Vous n\'êtes pas connecté.');
        }
        /** @var User $user */
        $user = $this->security->getUser();

        $entity = $this->messageThreadMetaRepository->find($uriVariables['id']);
        if (!$entity instanceof MessageThreadMeta) {
            throw new NotFoundHttpException('Message thread meta introuvable');
        }
        if ($entity->user->id !== $user->id) {
            throw new AccessDeniedException('Vous ne pouvez pas modifier ceci.');
        }

        // When the recipient catches up on the thread, reset the
        // one-email-per-unread-streak flag so the next incoming message can
        // trigger another notification (#533).
        if (!$entity->isRead && $data->isRead) {
            $entity->pendingNotificationSent = false;
        }

        $entity->isRead = $data->isRead;
        $this->entityManager->flush();

        return $this->messageThreadMetaBuilder->buildItem($entity);
    }
}
