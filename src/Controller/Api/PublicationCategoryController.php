<?php

namespace App\Controller\Api;

use App\Repository\PublicationSubCategoryRepository;
use App\Serializer\PublicationCategoryArraySerializer;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class PublicationCategoryController extends AbstractController
{
    /**
     * @Route("/api/publications/categories", name="api_publication_category_list", options={"expose": true})
     *
     * @param PublicationSubCategoryRepository $publicationSubCategoryRepository
     * @param PublicationCategoryArraySerializer $publicationCategoryArraySerializer
     *
     * @return JsonResponse
     */
    public function list(
        PublicationSubCategoryRepository $publicationSubCategoryRepository,
        PublicationCategoryArraySerializer $publicationCategoryArraySerializer
    ) {
        $categories = $publicationSubCategoryRepository->findAll();

        return $this->json([
            'data' => [
                'categories' => $publicationCategoryArraySerializer->listToArray($categories),
            ],
        ]);
    }
}
