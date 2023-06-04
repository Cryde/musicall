<?php

namespace App\EventSubscriber;

use ApiPlatform\Symfony\EventListener\EventPriorities;
use App\Contracts\AuthorableEntityInterface;
use App\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class AddAuthorToAuthorableEntitiesSubscriber implements EventSubscriberInterface
{
    public function __construct(private TokenStorageInterface $tokenStorage)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::VIEW => ['attachAuthor', EventPriorities::PRE_VALIDATE],
        ];
    }

    public function attachAuthor(ViewEvent $event)
    {
        $entity = $event->getControllerResult();
        $method = $event->getRequest()->getMethod();
        if (!$entity instanceof AuthorableEntityInterface || Request::METHOD_POST !== $method) {
            return;
        }
        if (!$token = $this->tokenStorage->getToken()) {
            return;
        }
        $author = $token->getUser();
        if (!$author instanceof User) {
            return;
        }
        $entity->setAuthor($author);
    }
}
