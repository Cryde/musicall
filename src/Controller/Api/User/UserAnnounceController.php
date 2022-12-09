<?php

namespace App\Controller\Api\User;

use App\Repository\Musician\MusicianAnnounceRepository;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

class UserAnnounceController extends AbstractController
{
    #[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
    #[Route(path: '/api/user/announce/musician', name: 'api_user_announce_musician_list', options: ['expose' => true], methods: ['GET'])]
    public function list(MusicianAnnounceRepository $musicianAnnounceRepository): JsonResponse
    {
        $announces = $musicianAnnounceRepository->findBy(['author' => $this->getUser()], ['creationDatetime' => 'DESC']);

        return $this->json($announces, Response::HTTP_OK, [], [
            AbstractNormalizer::IGNORED_ATTRIBUTES => ['author'],
        ]);
    }
}
