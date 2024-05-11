<?php

namespace App\Controller\Api;

use App\Repository\PublicationRepository;
use App\Serializer\PublicationSerializer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

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
}
