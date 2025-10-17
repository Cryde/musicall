<?php declare(strict_types=1);

namespace App\Controller\Api\Admin;

use App\Entity\Publication;
use App\Repository\PublicationRepository;
use App\Serializer\SmallPublicationSerializer;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class SearchController extends AbstractController
{
    #[IsGranted('ROLE_ADMIN')]
    #[Route(path: '/api/admin/search/publication', name: 'api_admin_search_publication', options: ['expose' => true])]
    public function publication(
        Request                    $request,
        PublicationRepository      $publicationRepository,
        SmallPublicationSerializer $smallPublicationSerializer
    ): JsonResponse {
        $query = $request->get('query', '');
        $publications = $publicationRepository->findByTitleAndStatusAndType(
            $query,
            Publication::STATUS_ONLINE,
            Publication::TYPE_TEXT
        );

        return $this->json($smallPublicationSerializer->listToArray($publications));
    }
}
