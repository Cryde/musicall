<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Serializer\User\UserArraySerializer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class SecurityController extends AbstractController
{
    /**
     * @Route("/api/login", name="api_login", methods={"POST"}, options={"expose":true})
     *
     * @param UserArraySerializer $userAppArraySerializer
     *
     * @return JsonResponse
     */
    public function login(UserArraySerializer $userAppArraySerializer)
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json([
            'data' => $userAppArraySerializer->toArray($user, true),
        ]);
    }
}
