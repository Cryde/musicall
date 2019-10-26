<?php

namespace App\Controller\Api;

use App\Entity\Publication;
use App\Entity\PublicationSubCategory;
use App\Repository\PublicationRepository;
use App\Serializer\PublicationSerializer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class PublicationController extends AbstractController
{
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
     * @param PublicationRepository $publicationRepository
     * @param PublicationSerializer $publicationSerializer
     *
     * @return JsonResponse
     */
    public function list(PublicationRepository $publicationRepository, PublicationSerializer $publicationSerializer)
    {
        $publications = $publicationRepository->findBy(['status' => Publication::STATUS_ONLINE], ['publicationDatetime' => 'DESC']);

        return $this->json(['data' => $publicationSerializer->listToArray($publications)]);
    }

    /**
     * @Route("api/publications/category/{slug}", name="api_publications_list_by_category", options={"expose":true})
     *
     * @param PublicationRepository $publicationRepository
     * @param PublicationSerializer $publicationSerializer
     *
     * @return JsonResponse
     */
    public function listByCategory(
        PublicationSubCategory $publicationSubCategory,
        PublicationRepository $publicationRepository,
        PublicationSerializer $publicationSerializer
    ) {
        $publications = $publicationRepository->findBy(['status' => Publication::STATUS_ONLINE, 'subCategory' => $publicationSubCategory], ['publicationDatetime' => 'DESC']);

        return $this->json(['data' => $publicationSerializer->listToArray($publications)]);
    }
}
