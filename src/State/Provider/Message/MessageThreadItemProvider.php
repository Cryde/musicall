<?php

declare(strict_types=1);

namespace App\State\Provider\Message;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Message\MessageThreadResource;
use App\Entity\Message\MessageThread;
use App\Entity\User;
use App\Repository\Message\MessageThreadRepository;
use App\Service\Access\ThreadAccess;
use App\Service\Builder\Message\MessageThreadBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<MessageThreadResource>
 */
readonly class MessageThreadItemProvider implements ProviderInterface
{
    public function __construct(
        private MessageThreadRepository $messageThreadRepository,
        private MessageThreadBuilder    $messageThreadBuilder,
        private ThreadAccess            $threadAccess,
        private Security                $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): MessageThreadResource
    {
        $entity = $this->messageThreadRepository->find($uriVariables['id']);
        if (!$entity instanceof MessageThread) {
            throw new NotFoundHttpException('Thread not found.');
        }

        // Participation check: throw 404 (not 403) for non-participants so authenticated
        // users cannot probe thread existence. Anonymous calls fall through; downstream
        // (Get operation security / processor auth) handle them.
        $user = $this->security->getUser();
        if ($user instanceof User && !$this->threadAccess->isOneOfParticipant($entity, $user)) {
            throw new NotFoundHttpException('Thread not found.');
        }

        return $this->messageThreadBuilder->buildItem($entity);
    }
}
