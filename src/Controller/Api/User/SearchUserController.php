<?php

namespace App\Controller\Api\User;

use App\Repository\UserRepository;
use App\Serializer\User\UserSearchArraySerializer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SearchUserController extends AbstractController
{
    /**
     * @Route(
     *     "/api/user/search",
     *     name="api_user_search",
     *     methods={"GET"},
     *     options={"expose": true}
     * )
     *
     * @param Request                   $request
     * @param UserRepository            $userRepository
     * @param UserSearchArraySerializer $userSearchArraySerializer
     *
     * @return JsonResponse
     */
    public function list(
        Request $request,
        UserRepository $userRepository,
        UserSearchArraySerializer $userSearchArraySerializer
    ) {
        $search = $request->get('search', '');

        if (strlen($search) < 4) {
            return $this->json([], Response::HTTP_NO_CONTENT);
        }

        $results = $userRepository->searchByUserName($search);

        return $this->json($userSearchArraySerializer->listToArray($results));
    }
}
