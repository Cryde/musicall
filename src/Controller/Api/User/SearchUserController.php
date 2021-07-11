<?php

namespace App\Controller\Api\User;

use App\Repository\UserRepository;
use App\Serializer\User\UserArraySerializer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SearchUserController extends AbstractController
{
    #[Route("/api/user/search", name: "api_user_search", options: ['expose' => true], methods: ['GET'])]
    public function list(
        Request $request,
        UserRepository $userRepository,
        UserArraySerializer $userArraySerializer
    ): JsonResponse {
        $search = $request->get('search', '');

        if (strlen($search) < 4) {
            return $this->json([], Response::HTTP_NO_CONTENT);
        }

        $results = $userRepository->searchByUserName($search);

        return $this->json($userArraySerializer->listToArray($results));
    }
}
