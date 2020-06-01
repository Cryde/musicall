<?php

namespace App\Controller\Api\User;

use App\Repository\Message\MessageThreadMetaRepository;
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
     *
     * @return JsonResponse
     */
    public function notifications(MessageThreadMetaRepository $messageThreadMetaRepository)
    {
        $unreadMessagesCount = $messageThreadMetaRepository->count(['user' => $this->getUser(), 'isRead' => 0]);

        return $this->json(['data' => [
            'messages' => $unreadMessagesCount
        ]]);
    }
}
