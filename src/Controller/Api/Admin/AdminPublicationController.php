<?php

namespace App\Controller\Api\Admin;

use App\Entity\Publication;
use App\Repository\PublicationRepository;
use App\Service\Builder\CommentThreadDirector;
use App\Service\Publication\PublicationSlug;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdminPublicationController extends AbstractController
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }
    /**
     * @Route("/api/admin/publications/pending", name="api_admin_publications_pending_list", methods={"GET"}, options={"expose": true})
     *
     * @IsGranted("ROLE_ADMIN")
     */
    public function listPending(PublicationRepository $publicationRepository): JsonResponse
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
     */
    public function approve(
        Publication $publication,
        PublicationSlug $publicationSlug,
        CommentThreadDirector $commentThreadDirector
    ): JsonResponse {
        $commentThread = $commentThreadDirector->create();
        $this->entityManager->persist($commentThread);

        $publication->setThread($commentThread);
        $publication->setPublicationDatetime(new \DateTime());
        $publication->setStatus(Publication::STATUS_ONLINE);
        $publication->setSlug($publicationSlug->create($publication->getTitle()));
        $this->entityManager->flush();

        return $this->json([]);
    }

    /**
     * @Route("/api/admin/publications/{id}/reject", name="api_admin_publications_reject", methods={"GET"}, options={"expose": true})
     *
     * @IsGranted("ROLE_ADMIN")
     */
    public function reject(Publication $publication): JsonResponse
    {
        $publication->setStatus(Publication::STATUS_DRAFT);
        $this->entityManager->flush();

        return $this->json([]);
    }
}
