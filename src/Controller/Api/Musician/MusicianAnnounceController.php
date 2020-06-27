<?php

namespace App\Controller\Api\Musician;

use App\Service\Builder\Musician\MusicianAnnounceDirector;
use App\Service\Jsonizer;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
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
     *
     * @param Request                  $request
     * @param Jsonizer                 $jsonizer
     * @param MusicianAnnounceDirector $musicianAnnounceDirector
     * @param ValidatorInterface       $validator
     *
     * @return JsonResponse
     */
    public function add(
        Request $request,
        Jsonizer $jsonizer,
        MusicianAnnounceDirector $musicianAnnounceDirector,
        ValidatorInterface $validator
    ) {
        $announce = $musicianAnnounceDirector->createFromArray($jsonizer->decodeRequest($request));
        $announce->setAuthor($this->getUser());

        $errors = $validator->validate($announce);

        if (count($errors) > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $this->getDoctrine()->getManager()->persist($announce);
        $this->getDoctrine()->getManager()->flush();

        return $this->json([], Response::HTTP_CREATED);
    }
}
