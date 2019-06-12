<?php

namespace App\Controller\Api;

use App\Entity\Publication;
use App\Repository\PublicationRepository;
use App\Serializer\UserPublicationArraySerializer;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UserPublicationController extends AbstractController
{
    /**
     * @Route("/api/users/publications/", name="api_user_publication_list", options={"expose": true})
     *
     * @IsGranted("IS_AUTHENTICATED_REMEMBERED")
     *
     * @param PublicationRepository          $publicationRepository
     * @param UserPublicationArraySerializer $userPublicationArraySerializer
     *
     * @return JsonResponse
     */
    public function list(
        PublicationRepository $publicationRepository,
        UserPublicationArraySerializer $userPublicationArraySerializer
    ) {
        $publications = $publicationRepository->findBy(['author' => $this->getUser()]);
        return $this->json(['publications' => $userPublicationArraySerializer->listToArray($publications)]);
    }

    /**
     * @Route("/api/users/publications/{id}/delete", name="api_user_publication_delete", options={"expose": true})
     *
     * @IsGranted("IS_AUTHENTICATED_REMEMBERED")
     *
     * @param Publication $publication
     *
     * @return JsonResponse
     */
    public function remove(Publication $publication)
    {
        if ($this->getUser()->getId() !== $publication->getAuthor()->getId()) {
            return $this->json(['data' => ['success' => 0, 'message' => 'Ce publication ne vous appartient pas']], Response::HTTP_FORBIDDEN);
        }

        if ($publication->getStatus() !== Publication::STATUS_DRAFT) {
            return $this->json(['data' => ['success' => 0, 'message' => 'Vous ne pouvez pas supprimer une publication en ligne ou en review']], Response::HTTP_FORBIDDEN);
        }

        $this->getDoctrine()->getManager()->remove($publication);
        $this->getDoctrine()->getManager()->flush();

        return $this->json(['data' => ['success' => 1]]);
    }
}
