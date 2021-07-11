<?php

namespace App\Controller\Api\Publication;

use App\Repository\PublicationSubCategoryRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PublicationCategoryController extends AbstractController
{
    /**@Cache(expires="+2 weeks", public=true) */
    #[Route("/api/publications/categories", name: 'api_publication_category_list', options: ['expose' => true], methods: ['GET'], priority: 30)]
    public function list(PublicationSubCategoryRepository $publicationSubCategoryRepository): JsonResponse
    {
        $categories = $publicationSubCategoryRepository->findBy([], ['position' => 'ASC']);

        return $this->json($categories, Response::HTTP_OK, [], ['ignored_attributes' => ['publications']]);
    }
}
