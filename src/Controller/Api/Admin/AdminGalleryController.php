<?php

namespace App\Controller\Api\Admin;

use App\Entity\Gallery;
use App\Repository\GalleryRepository;
use App\Service\Publication\GallerySlug;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdminGalleryController extends AbstractController
{
    /**
     * @Route("/api/admin/gallery/pending", name="api_admin_gallery_pending_list", methods={"GET"}, options={"expose": true})
     *
     * @IsGranted("ROLE_ADMIN")
     *
     * @param GalleryRepository $galleryRepository
     *
     * @return JsonResponse
     */
    public function listPending(GalleryRepository $galleryRepository)
    {
        $pendingGalleries = $galleryRepository->findBy(['status' => Gallery::STATUS_PENDING]);

        return $this->json($pendingGalleries, Response::HTTP_OK, [], [
            'attributes' => ['id', 'title', 'slug', 'author' => ['username']]
        ]);
    }

    /**
     * @Route("/api/admin/gallery/{id}/approve", name="api_admin_gallery_approve", methods={"GET"}, options={"expose": true})
     *
     * @IsGranted("ROLE_ADMIN")
     *
     * @param Gallery     $gallery
     * @param GallerySlug $gallerySlug
     *
     * @return JsonResponse
     * @throws \Exception
     */
    public function approve(Gallery $gallery, GallerySlug $gallerySlug)
    {
        $gallery->setPublicationDatetime(new \DateTime());
        $gallery->setStatus(Gallery::STATUS_ONLINE);
        $gallery->setSlug($gallerySlug->create($gallery->getTitle()));
        $this->getDoctrine()->getManager()->flush();

        return $this->json([]);
    }

    /**
     * @Route("/api/admin/gallery/{id}/reject", name="api_admin_gallery_reject", methods={"GET"}, options={"expose": true})
     *
     * @IsGranted("ROLE_ADMIN")
     *
     * @param Gallery $gallery
     *
     * @return JsonResponse
     */
    public function reject(Gallery $gallery)
    {
        $gallery->setStatus(Gallery::STATUS_DRAFT);
        $this->getDoctrine()->getManager()->flush();

        return $this->json([]);
    }
}
