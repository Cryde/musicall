<?php

namespace App\Processor\Message;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Message\Message;
use App\Entity\User;
use App\Service\Access\ThreadAccess;
use App\Service\Procedure\Message\MessageSenderProcedure;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class MessagePostProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly Security               $security,
        private readonly ThreadAccess           $threadAccess,
        private readonly MessageSenderProcedure $messageSenderProcedure
    ) {
    }

    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var Message $data */
        if (!$this->security->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            throw new AccessDeniedException('Vous n\'êtes pas connecté.');
        }
        /** @var User $user */
        $user = $this->security->getUser();
        $thread = $data->getThread();
        if (!$this->threadAccess->isOneOfParticipant($thread, $user)) {
            throw new AccessDeniedException('Vous n\'êtes pas autorisé à voir ceci.');
        }

        return $this->messageSenderProcedure->processByThread($thread, $user, $data->getContent());
    }
}