<?php

namespace App\Provider\Notification;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Gallery;
use App\Entity\Publication;
use App\Model\Notification\Notification;
use App\Repository\GalleryRepository;
use App\Repository\Message\MessageThreadMetaRepository;
use App\Repository\PublicationRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class NotificationProvider implements ProviderInterface
{
    public function __construct(
        private readonly Security                    $security,
        private readonly MessageThreadMetaRepository $messageThreadMetaRepository,
        private readonly GalleryRepository           $galleryRepository,
        private readonly PublicationRepository       $publicationRepository
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|null|object
    {
        if (!$this->security->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            throw new AccessDeniedException('Vous n\'êtes pas connecté.');
        }
        $user = $this->security->getUser();
        $unreadMessagesCount = $this->messageThreadMetaRepository->count(['user' => $user, 'isRead' => 0]);
        $notification = (new Notification())->setUnreadMessages($unreadMessagesCount);
        if ($this->security->isGranted('ROLE_ADMIN')) {
            $pendingGalleriesCount = $this->galleryRepository->count(['status' => Gallery::STATUS_PENDING]);
            $pendingPublicationCount = $this->publicationRepository->count(['status' => Publication::STATUS_PENDING]);

            return $notification
                ->setPendingGalleries($pendingGalleriesCount)
                ->setPendingPublications($pendingPublicationCount);
        }

        return $notification;
    }
}