<?php

namespace App\Controller\Api\Publication;

use App\Entity\Publication;
use App\Entity\PublicationSubCategory;
use App\Repository\PublicationRepository;
use App\Repository\PublicationSubCategoryRepository;
use App\Serializer\Publication\SubCategoryArraySerializer;
use App\Serializer\PublicationSerializer;
use App\Service\Procedure\Metric\ViewProcedure;
use Doctrine\ORM\NonUniqueResultException;
use HtmlSanitizer\SanitizerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class PublicationController extends AbstractController
{
    final const LIMIT_PUBLICATION_BY_PAGE = 16;

    #[Route("/api/publications/{slug}", name: "api_publications_show", options: ['expose' => true], priority: 2)]
    public function show(
        Request                    $request,
        Publication                $publication,
        SanitizerInterface         $publicationsSanitizer,
        SubCategoryArraySerializer $categoryArraySerializer,
        ViewProcedure              $viewProcedure
    ): JsonResponse {
        if ($publication->getStatus() === Publication::STATUS_ONLINE) {
            $viewProcedure->process($publication, $request, $this->getUser());
        }

        return $this->json([
            'author'               => [
                'username' => $publication->getAuthor()->getUserIdentifier(),
            ],
            'title'                => $publication->getTitle(),
            'description'          => $publication->getShortDescription(),
            'publication_datetime' => $publication->getPublicationDatetime(),
            'category'             => $categoryArraySerializer->toArray($publication->getSubCategory()),
            'content'              => $publicationsSanitizer->sanitize($publication->getContent()),
            'type'                 => $publication->getType() === Publication::TYPE_VIDEO ? Publication::TYPE_VIDEO_LABEL : Publication::TYPE_TEXT_LABEL,
            'thread'               => ['id' => $publication->getThread() ? $publication->getThread()->getId() : null],
        ]);
    }

    #[Route("api/publications", name: "api_publications_list", options: ["expose" => true])]
    public function list(
        Request                          $request,
        PublicationRepository            $publicationRepository,
        PublicationSubCategoryRepository $publicationSubCategoryRepository,
        PublicationSerializer            $publicationSerializer
    ): JsonResponse {
        $offset = $request->get('offset', 0);
        $calculatedOffset = $offset ? $offset * self::LIMIT_PUBLICATION_BY_PAGE : 0;
        $total = $publicationRepository->countOnlinePublications();
        $categories = $publicationSubCategoryRepository->findAll();
        $publications = $publicationRepository->findOnlinePublications(
            $categories,
            $calculatedOffset,
            self::LIMIT_PUBLICATION_BY_PAGE
        );

        return $this->json([
            'data' => [
                'meta'         => [
                    'numberOfPages' => ceil($total / self::LIMIT_PUBLICATION_BY_PAGE),
                    'total'         => $total,
                    'limit_by_page' => self::LIMIT_PUBLICATION_BY_PAGE,
                ],
                'publications' => $publicationSerializer->listToArray($publications),
            ],
        ]);
    }

    /**
     * @throws NonUniqueResultException
     */
    #[Route(path: '/api/publications/category/{slug}', name: 'api_publications_list_by_category', options: ['expose' => true], priority: 1)]
    public function listByCategory(
        Request                $request,
        PublicationSubCategory $publicationSubCategory,
        PublicationRepository  $publicationRepository,
        PublicationSerializer  $publicationSerializer
    ): JsonResponse {
        $offset = $request->get('offset', 0);
        $calculatedOffset = $offset ? $offset * self::LIMIT_PUBLICATION_BY_PAGE : 0;
        $total = $publicationRepository->countOnlinePublicationsByCategory($publicationSubCategory);
        $publications = $publicationRepository->findOnlinePublicationsByCategory($publicationSubCategory, $calculatedOffset, self::LIMIT_PUBLICATION_BY_PAGE);

        return $this->json([
            'data' => [
                'meta'         => [
                    'numberOfPages' => ceil($total / self::LIMIT_PUBLICATION_BY_PAGE),
                    'total'         => $total,
                    'limit_by_page' => self::LIMIT_PUBLICATION_BY_PAGE,
                ],
                'publications' => $publicationSerializer->listToArray($publications),
            ],
        ]);
    }
}
