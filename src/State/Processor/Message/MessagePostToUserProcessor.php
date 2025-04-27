<?php

namespace App\State\Processor\Message;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Message\MessageUser;
use App\Entity\User;
use App\Service\Procedure\Message\MessageSenderProcedure;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class MessagePostToUserProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly Security               $security,
        private readonly MessageSenderProcedure $messageSenderProcedure
    ) {
    }

    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var MessageUser $data */
        if (!$this->security->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            throw new AccessDeniedException('Vous n\'êtes pas connecté.');
        }
        /** @var User $currentUser */
        $currentUser = $this->security->getUser();

        return $this->messageSenderProcedure->process($currentUser, $data->recipient, $data->content);
    }
}