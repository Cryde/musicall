<?php

namespace App\Controller\Api\Musician;

use App\Service\Builder\Musician\MusicianAnnounceDirector;
use App\Service\Jsonizer;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class MusicianAnnounceController extends AbstractController
{
    /**
     * @Route(
     *     "/api/musician/announce/add",
     *     name="api_musician_announce_add",
     *     methods={"POST"},
     *     options={"expose": true}
     * )
     *
     * @IsGranted("IS_AUTHENTICATED_REMEMBERED")
     */
    public function add(
        Request $request,
        Jsonizer $jsonizer,
        MusicianAnnounceDirector $musicianAnnounceDirector,
        ValidatorInterface $validator,
        EntityManagerInterface $entityManager,
        #[CurrentUser] $user
    ): JsonResponse {
        $announce = $musicianAnnounceDirector->createFromArray($jsonizer->decodeRequest($request));
        $announce->setAuthor($user);

        $errors = $validator->validate($announce);

        if (count($errors) > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $entityManager->persist($announce);
        $entityManager->flush();

        return $this->json([], Response::HTTP_CREATED);
    }
}
