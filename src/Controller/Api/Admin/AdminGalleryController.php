<?php declare(strict_types=1);

namespace App\Controller\Api\Admin;

use App\Entity\Gallery;
use App\Repository\GalleryRepository;
use App\Service\Publication\GallerySlug;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdminGalleryController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route(path: '/api/admin/gallery/pending', name: 'api_admin_gallery_pending_list', options: ['expose' => true], methods: ['GET'])]
    public function listPending(GalleryRepository $galleryRepository): JsonResponse
    {
        $pendingGalleries = $galleryRepository->findBy(['status' => Gallery::STATUS_PENDING]);

        return $this->json($pendingGalleries, Response::HTTP_OK, [], [
            'attributes' => ['id', 'title', 'slug', 'author' => ['username']],
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route(path: '/api/admin/gallery/{id}/approve', name: 'api_admin_gallery_approve', options: ['expose' => true], methods: ['GET'])]
    public function approve(Gallery $gallery, GallerySlug $gallerySlug): JsonResponse
    {
        $gallery->setPublicationDatetime(new \DateTime());
        $gallery->setStatus(Gallery::STATUS_ONLINE);
        $gallery->setSlug($gallerySlug->create($gallery->getTitle()));
        $this->entityManager->flush();

        return $this->json([]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route(path: '/api/admin/gallery/{id}/reject', name: 'api_admin_gallery_reject', options: ['expose' => true], methods: ['GET'])]
    public function reject(Gallery $gallery): JsonResponse
    {
        $gallery->setStatus(Gallery::STATUS_DRAFT);
        $this->entityManager->flush();

        return $this->json([]);
    }
}
