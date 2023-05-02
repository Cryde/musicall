<?php

namespace App\Processor\Message;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Message\MessageThreadMeta;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class MessageThreadMetaPatchProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly Security               $security,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        if ($operation instanceof Patch) {
            if (!$this->security->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
                throw new AccessDeniedException('Vous n\'êtes pas connecté.');
            }
            /** @var MessageThreadMeta $data */
            /** @var User $user */
            $user = $this->security->getUser();
            if ($user->getId() !== $data->getUser()->getId()) {
                throw new AccessDeniedException('Vous ne pouvez pas modifier ceci.');
            }
            $this->entityManager->flush();

            return $data;
        }
        throw new \InvalidArgumentException('Operation not supported by the provider');
    }
}