<?php

namespace App\Controller\Api\Admin;

use App\Entity\Publication;
use App\Repository\PublicationRepository;
use App\Service\Builder\CommentThreadDirector;
use App\Service\Publication\PublicationSlug;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdminPublicationController extends AbstractController
{
    /**
     * @Route("/api/admin/publications/pending", name="api_admin_publications_pending_list", methods={"GET"}, options={"expose": true})
     *
     * @IsGranted("ROLE_ADMIN")
     *
     * @param PublicationRepository $publicationRepository
     *
     * @return JsonResponse
     */
    public function listPending(PublicationRepository $publicationRepository)
    {
        $pendingPublication = $publicationRepository->findBy(['status' => Publication::STATUS_PENDING]);

        return $this->json($pendingPublication, Response::HTTP_OK, [], [
            'attributes' => ['id', 'title', 'slug', 'author' => ['username'], 'subCategory' => ['title']]
        ]);
    }

    /**
     * @Route("/api/admin/publications/{id}/approve", name="api_admin_publications_approve", methods={"GET"}, options={"expose": true})
     *
     * @IsGranted("ROLE_ADMIN")
     *
     * @param Publication           $publication
     * @param PublicationSlug       $publicationSlug
     * @param CommentThreadDirector $commentThreadDirector
     *
     * @return JsonResponse
     */
    public function approve(
        Publication $publication,
        PublicationSlug $publicationSlug,
        CommentThreadDirector $commentThreadDirector
    ) {
        $commentThread = $commentThreadDirector->create();
        $this->getDoctrine()->getManager()->persist($commentThread);

        $publication->setThread($commentThread);
        $publication->setPublicationDatetime(new \DateTime());
        $publication->setStatus(Publication::STATUS_ONLINE);
        $publication->setSlug($publicationSlug->create($publication->getTitle()));
        $this->getDoctrine()->getManager()->flush();

        return $this->json([]);
    }

    /**
     * @Route("/api/admin/publications/{id}/reject", name="api_admin_publications_reject", methods={"GET"}, options={"expose": true})
     *
     * @IsGranted("ROLE_ADMIN")
     *
     * @param Publication $publication
     *
     * @return JsonResponse
     */
    public function reject(Publication $publication)
    {
        $publication->setStatus(Publication::STATUS_DRAFT);
        $this->getDoctrine()->getManager()->flush();

        return $this->json([]);
    }
}
