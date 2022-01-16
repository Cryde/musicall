<?php

namespace App\Controller\Api\Publication;

use App\Entity\PublicationFeatured;
use App\Repository\PublicationFeaturedRepository;
use App\Serializer\PublicationFeaturedSerializer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class PublicationFeaturedController extends AbstractController
{
    /**
     * @Route(
     *     "/api/publication/featured",
     *     name="api_publication_featured_list",
     *     options={"expose": true}
     * )
     */
    public function list(
        PublicationFeaturedRepository $publicationFeaturedRepository,
        PublicationFeaturedSerializer $publicationFeaturedSerializer
    ): JsonResponse {
        return $this->json($publicationFeaturedSerializer->toList($publicationFeaturedRepository->findBy(['status' => PublicationFeatured::STATUS_ONLINE])));
    }
}
