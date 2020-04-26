<?php

namespace App\Controller\Api;

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
     *
     * @param PublicationFeaturedRepository $publicationFeaturedRepository
     * @param PublicationFeaturedSerializer $publicationFeaturedSerializer
     *
     * @return JsonResponse
     */
    public function list(
        PublicationFeaturedRepository $publicationFeaturedRepository,
        PublicationFeaturedSerializer $publicationFeaturedSerializer
    ) {
        return $this->json($publicationFeaturedSerializer->toList($publicationFeaturedRepository->findBy(['status' => PublicationFeatured::STATUS_ONLINE])));
    }
}
