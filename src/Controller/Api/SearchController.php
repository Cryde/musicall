<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Model\Search\Musician;
use App\Repository\Musician\MusicianAnnounceRepository;
use App\Repository\PublicationRepository;
use App\Serializer\PublicationSerializer;
use App\Serializer\Search\MusicianSearchArraySerializer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

class SearchController extends AbstractController
{
    /**
     * @Route("/api/search", name="api_search", options={"expose" = true})
     *
     * @param Request               $request
     * @param PublicationRepository $publicationRepository
     * @param PublicationSerializer $publicationSerializer
     *
     * @return JsonResponse
     */
    public function search(Request $request, PublicationRepository $publicationRepository, PublicationSerializer $publicationSerializer)
    {
        $term = $request->get('term');

        if(strlen($term) < 3) {
            return $this->json([], Response::HTTP_NO_CONTENT);
        }

        $publications = $publicationRepository->getBySearchTerm($term);

        return $this->json($publicationSerializer->listToArray($publications));
    }

    /**
     * @Route(
     *     "/api/search/musician",
     *     name="api_search_musician",
     *     methods={"POST"},
     *     options={"expose" = true}
     * )
     *
     * @param Request                       $request
     * @param SerializerInterface           $serializer
     * @param MusicianAnnounceRepository    $musicianAnnounceRepository
     * @param MusicianSearchArraySerializer $musicianSearchArraySerializer
     *
     * @return JsonResponse
     */
    public function searchMusician(
        Request $request,
        SerializerInterface $serializer,
        MusicianAnnounceRepository $musicianAnnounceRepository,
        MusicianSearchArraySerializer $musicianSearchArraySerializer
    ) {
        /** @var Musician $musicianModel */
        $musicianModel = $serializer->deserialize($request->getContent(), Musician::class, 'json');

        /** @var User|null $user */
        $user = $this->getUser();
        $results = $musicianAnnounceRepository->findByCriteria($musicianModel, $user);
        $serializedData = $musicianSearchArraySerializer->listToArray($results);

        return $this->json(['data' => $serializedData]);
    }
}
