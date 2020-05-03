<?php

namespace App\Controller\Api;

use App\Repository\PublicationSubCategoryRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PublicationCategoryController extends AbstractController
{
    /**
     * @Route("/api/publications/categories", name="api_publication_category_list", methods={"GET"}, options={"expose": true})
     * @Cache(expires="+2 weeks", public=true)
     * @param PublicationSubCategoryRepository $publicationSubCategoryRepository
     *
     * @return JsonResponse
     */
    public function list(PublicationSubCategoryRepository $publicationSubCategoryRepository)
    {
        $categories = $publicationSubCategoryRepository->findBy([], ['position' => 'ASC']);

        return $this->json($categories, Response::HTTP_OK, [], ['ignored_attributes' => ['publications']]);
    }
}
