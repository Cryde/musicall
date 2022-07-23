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
    #[Route(path: '/api/search', name: 'api_search', options: ['expose' => true])]
    public function search(
        Request               $request,
        PublicationRepository $publicationRepository,
        PublicationSerializer $publicationSerializer
    ): JsonResponse {
        $term = $request->get('term', '');
        if (strlen((string)$term) < 3) {
            return $this->json([], Response::HTTP_NO_CONTENT);
        }
        $publications = $publicationRepository->getBySearchTerm($term);

        return $this->json($publicationSerializer->listToArray($publications));
    }

    #[Route(path: '/api/search/musician', name: 'api_search_musician', options: ['expose' => true], methods: ['POST'])]
    public function searchMusician(
        Request                       $request,
        SerializerInterface           $serializer,
        MusicianAnnounceRepository    $musicianAnnounceRepository,
        MusicianSearchArraySerializer $musicianSearchArraySerializer
    ): JsonResponse {
        /** @var Musician $musicianModel */
        $musicianModel = $serializer->deserialize($request->getContent(), Musician::class, 'json');
        /** @var User|null $user */
        $user = $this->getUser();
        $results = $musicianAnnounceRepository->findByCriteria($musicianModel, $user);
        $serializedData = $musicianSearchArraySerializer->listToArray($results);

        return $this->json(['data' => $serializedData]);
    }
}
