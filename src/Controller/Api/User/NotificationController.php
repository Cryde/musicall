<?php

namespace App\Controller\Api\User;

use App\Entity\Gallery;
use App\Entity\Publication;
use App\Repository\GalleryRepository;
use App\Repository\Message\MessageThreadMetaRepository;
use App\Repository\PublicationRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class NotificationController extends AbstractController
{
    /**
     * @Route("/api/users/notifications", name="api_user_notifications", methods={"GET"}, options={"expose": true})
     *
     * @IsGranted("IS_AUTHENTICATED_REMEMBERED")
     *
     * @param MessageThreadMetaRepository $messageThreadMetaRepository
     * @param GalleryRepository           $galleryRepository
     * @param PublicationRepository       $publicationRepository
     *
     * @return JsonResponse
     */
    public function notifications(
        MessageThreadMetaRepository $messageThreadMetaRepository,
        GalleryRepository $galleryRepository,
        PublicationRepository $publicationRepository
    ) {
        $unreadMessagesCount = $messageThreadMetaRepository->count(['user' => $this->getUser(), 'isRead' => 0]);

        if ($this->isGranted('ROLE_ADMIN')) {
            $pendingGalleriesCount = $galleryRepository->count(['status' => Gallery::STATUS_PENDING]);
            $pendingPublicationCount = $publicationRepository->count(['status' => Publication::STATUS_PENDING]);

            return $this->json([
                'data' => [
                    'messages' => $unreadMessagesCount,
                    'admin'    => [
                        'pending_gallery'     => $pendingGalleriesCount,
                        'pending_publication' => $pendingPublicationCount,
                    ],
                ],
            ]);
        }

        return $this->json(['data' => [
            'messages' => $unreadMessagesCount
        ]]);
    }
}
