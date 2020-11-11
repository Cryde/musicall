<?php

namespace App\Controller\Api\Publication;

use App\Entity\Publication;
use App\Entity\PublicationSubCategory;
use App\Repository\PublicationRepository;
use App\Repository\PublicationSubCategoryRepository;
use App\Serializer\Publication\SubCategoryArraySerializer;
use App\Serializer\PublicationSerializer;
use App\Service\Procedure\Metric\ViewProcedure;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class PublicationController extends AbstractController
{
    const LIMIT_PUBLICATION_BY_PAGE = 16;

    /**
     * @Route(
     *     "/api/publications/{slug}",
     *     name="api_publications_show",
     *     options={"expose":true},
     *     priority=2
     * )
     *
     * @param Request                    $request
     * @param Publication                $publication
     * @param \HTMLPurifier              $purifier
     * @param SubCategoryArraySerializer $categoryArraySerializer
     * @param ViewProcedure              $viewProcedure
     *
     * @return JsonResponse
     */
    public function show(
        Request $request,
        Publication $publication,
        \HTMLPurifier $purifier,
        SubCategoryArraySerializer $categoryArraySerializer,
        ViewProcedure $viewProcedure
    ) {
        if ($publication->getStatus() === Publication::STATUS_ONLINE) {
            $viewProcedure->process($publication, $request, $this->getUser());
        }

        return $this->json([
            'author'               => [
                'username' => $publication->getAuthor()->getUsername(),
            ],
            'title'                => $publication->getTitle(),
            'description'          => $publication->getShortDescription(),
            'publication_datetime' => $publication->getPublicationDatetime(),
            'category'             => $categoryArraySerializer->toArray($publication->getSubCategory()),
            'content'              => $purifier->purify($publication->getContent()),
            'type'                 => $publication->getType() === Publication::TYPE_VIDEO ? Publication::TYPE_VIDEO_LABEL : Publication::TYPE_TEXT_LABEL,
            'thread'               => ['id' => $publication->getThread() ? $publication->getThread()->getId() : null],
        ]);
    }

    /**
     * @Route("api/publications", name="api_publications_list", options={"expose":true})
     *
     * @param Request                          $request
     * @param PublicationRepository            $publicationRepository
     * @param PublicationSubCategoryRepository $publicationSubCategoryRepository
     * @param PublicationSerializer            $publicationSerializer
     *
     * @return JsonResponse
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function list(
        Request $request,
        PublicationRepository $publicationRepository,
        PublicationSubCategoryRepository $publicationSubCategoryRepository,
        PublicationSerializer $publicationSerializer
    ) {
        $offset = $request->get('offset', 0);
        $calculatedOffset = $offset ? $offset * self::LIMIT_PUBLICATION_BY_PAGE : 0;
        $total = $publicationRepository->countOnlinePublications();
        $categories = $publicationSubCategoryRepository->findBy(['type' => PublicationSubCategory::TYPE_PUBLICATION]);
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
     * @Route(
     *     "/api/publications/category/{slug}",
     *     name="api_publications_list_by_category",
     *     options={"expose":true},
     *     priority=1
     * )
     *
     * @param Request                $request
     * @param PublicationSubCategory $publicationSubCategory
     * @param PublicationRepository  $publicationRepository
     * @param PublicationSerializer  $publicationSerializer
     *
     * @return JsonResponse
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function listByCategory(
        Request $request,
        PublicationSubCategory $publicationSubCategory,
        PublicationRepository $publicationRepository,
        PublicationSerializer $publicationSerializer
    ) {
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
