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
}
