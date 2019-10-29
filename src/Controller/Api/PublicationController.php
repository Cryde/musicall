<?php

namespace App\Controller\Api;

use App\Entity\Publication;
use App\Entity\PublicationSubCategory;
use App\Repository\PublicationRepository;
use App\Serializer\PublicationSerializer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class PublicationController extends AbstractController
{
    const LIMIT_PUBLICATION_BY_PAGE = 20;

    /**
     * @Route("api/publications/{slug}", name="api_publications_show", options={"expose":true})
     *
     * @param Publication   $publication
     * @param \HTMLPurifier $purifier
     *
     * @return JsonResponse
     */
    public function show(Publication $publication, \HTMLPurifier $purifier)
    {
        return $this->json([
            'title'   => $publication->getTitle(),
            'content' => $purifier->purify($publication->getContent()),
            'type'    => $publication->getType() === Publication::TYPE_VIDEO ? 'video' : 'text',
        ]);
    }

    /**
     * @Route("api/publications", name="api_publications_list", options={"expose":true})
     *
     * @param Request               $request
     * @param PublicationRepository $publicationRepository
     * @param PublicationSerializer $publicationSerializer
     *
     * @return JsonResponse
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function list(
        Request $request,
        PublicationRepository $publicationRepository,
        PublicationSerializer $publicationSerializer
    ) {
        $offset = $request->get('offset', 0);
        $calculatedOffset = $offset ? $offset * self::LIMIT_PUBLICATION_BY_PAGE : 0;

        $total = $publicationRepository->countOnlinePublications();
        $publications = $publicationRepository->findOnlinePublications($calculatedOffset, self::LIMIT_PUBLICATION_BY_PAGE);

        return $this->json([
            'data' => [
                'meta'         => ['numberOfPages' => ceil($total / self::LIMIT_PUBLICATION_BY_PAGE)],
                'publications' => $publicationSerializer->listToArray($publications),
            ],
        ]);
    }

    /**
     * @Route("api/publications/category/{slug}", name="api_publications_list_by_category", options={"expose":true})
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
                'meta'         => ['numberOfPages' => ceil($total / self::LIMIT_PUBLICATION_BY_PAGE)],
                'publications' => $publicationSerializer->listToArray($publications),
            ],
        ]);
    }
}
