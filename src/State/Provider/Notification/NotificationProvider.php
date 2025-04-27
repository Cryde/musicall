<?php

namespace App\State\Provider\Notification;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Notification\Notification;
use App\Entity\Gallery;
use App\Entity\Publication;
use App\Repository\GalleryRepository;
use App\Repository\Message\MessageThreadMetaRepository;
use App\Repository\PublicationRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

readonly class NotificationProvider implements ProviderInterface
{
    public function __construct(
        private Security                    $security,
        private MessageThreadMetaRepository $messageThreadMetaRepository,
        private GalleryRepository           $galleryRepository,
        private PublicationRepository       $publicationRepository
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|null|object
    {
        if (!$this->security->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            throw new AccessDeniedException('Vous n\'êtes pas connecté.');
        }
        $user = $this->security->getUser();
        $unreadMessagesCount = $this->messageThreadMetaRepository->count(['user' => $user, 'isRead' => 0]);
        $notification = new Notification();
        $notification->unreadMessages = $unreadMessagesCount;
        if ($this->security->isGranted('ROLE_ADMIN')) {
            $notification->pendingGalleries = $this->galleryRepository->count(['status' => Gallery::STATUS_PENDING]);
            $notification->pendingPublications = $this->publicationRepository->count(['status' => Publication::STATUS_PENDING]);

            return $notification;
        }

        return $notification;
    }
}