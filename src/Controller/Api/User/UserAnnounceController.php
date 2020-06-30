<?php

namespace App\Controller\Api\User;
use App\Repository\Musician\MusicianAnnounceRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

class UserAnnounceController extends AbstractController
{
    /**
     * @Route(
     *     "/api/user/announce/musician",
     *     name="api_user_announce_musician_list",
     *     methods={"GET"},
     *     options={"expose": true}
     * )
     *
     * @IsGranted("IS_AUTHENTICATED_REMEMBERED")
     *
     * @param MusicianAnnounceRepository $musicianAnnounceRepository
     *
     * @return JsonResponse
     */
    public function list(MusicianAnnounceRepository $musicianAnnounceRepository)
    {
        $announces = $musicianAnnounceRepository->findBy(['author' => $this->getUser()], ['creationDatetime' => 'DESC']);

        return $this->json($announces, Response::HTTP_OK, [], [
            AbstractNormalizer::IGNORED_ATTRIBUTES => ['author']
        ]);
    }
}
