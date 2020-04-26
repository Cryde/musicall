<?php

namespace App\Controller\Api\Admin;

use App\Entity\Publication;
use App\Repository\PublicationRepository;
use App\Serializer\SmallPublicationSerializer;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class SearchController extends AbstractController
{
    /**
     * @Route(
     *     "/api/admin/search/publication",
     *     name="api_admin_search_publication",
     *     options={"expose": true}
     * )
     *
     * @IsGranted("ROLE_ADMIN")
     *
     * @param Request                    $request
     * @param PublicationRepository      $publicationRepository
     * @param SmallPublicationSerializer $smallPublicationSerializer
     *
     * @return JsonResponse
     */
    public function publication(
        Request $request,
        PublicationRepository $publicationRepository,
        SmallPublicationSerializer $smallPublicationSerializer
    ) {
        $query = $request->get('query', '');

        $publications = $publicationRepository->findByTitleAndStatusAndType(
            $query,
            Publication::STATUS_ONLINE,
            Publication::TYPE_TEXT
        );

        return $this->json($smallPublicationSerializer->listToArray($publications));
    }
}
