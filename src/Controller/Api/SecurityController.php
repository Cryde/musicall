<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Serializer\User\UserArraySerializer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class SecurityController extends AbstractController
{
    #[Route(path: '/api/login', name: 'api_login', options: ['expose' => true], methods: ['POST'])]
    public function login(UserArraySerializer $userAppArraySerializer): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json([
            'data' => $userAppArraySerializer->toArray($user, true),
        ]);
    }
}
