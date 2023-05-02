<?php

namespace App\Processor\Message;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\Model\Message\MessageUser;
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
        if ($operation instanceof Post) {
            if (!$this->security->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
                throw new AccessDeniedException('Vous n\'êtes pas connecté.');
            }
            /** @var User $currentUser */
            $currentUser = $this->security->getUser();

            return $this->messageSenderProcedure->process($currentUser, $data->getRecipient(), $data->getContent());
        }
        throw new \InvalidArgumentException('Operation not supported by the provider');
    }
}