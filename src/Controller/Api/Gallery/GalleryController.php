<?php

namespace App\Controller\Api\Gallery;

use App\Entity\Gallery;
use App\Repository\GalleryRepository;
use App\Serializer\GalleryImageSerializer;
use App\Serializer\Normalizer\GalleryNormalizer;
use App\Service\Procedure\Metric\ViewProcedure;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

class GalleryController extends AbstractController
{
    /**
     * @Route("/api/gallery", name="api_gallery_list", options={"expose": true})
     *
     * @param GalleryRepository $galleryRepository
     *
     * @return JsonResponse
     */
    public function list(GalleryRepository $galleryRepository)
    {
        $galleries = $galleryRepository->findBy(['status' => Gallery::STATUS_ONLINE], ['publicationDatetime' => 'DESC']);

        // todo probablement pagination !
        return $this->json($galleries, Response::HTTP_OK, [], [
            GalleryNormalizer::CONTEXT_GALLERY => true
        ]);
    }

    /**
     * @Route("/api/gallery/{slug}", name="api_gallery_show", options={"expose": true})
     *
     * @param Request       $request
     * @param Gallery       $gallery
     * @param ViewProcedure $viewProcedure
     *
     * @return JsonResponse
     */
    public function show(Request $request, Gallery $gallery, ViewProcedure $viewProcedure)
    {
        if ($gallery->getStatus() === Gallery::STATUS_ONLINE) {
            $viewProcedure->process($gallery, $request, $this->getUser());
        }

        return $this->json($gallery, Response::HTTP_OK, [], [
            AbstractNormalizer::ATTRIBUTES => [
                'author' => ['username'],
                'title',
                'description',
                'publicationDatetime',
            ],
        ]);
    }

    /**
     * @Route("/api/gallery/{slug}/images", name="api_gallery_images_show", options={"expose": true})
     *
     * @param Gallery                $gallery
     * @param GalleryImageSerializer $galleryImageSerializer
     *
     * @return JsonResponse
     */
    public function images(Gallery $gallery, GalleryImageSerializer $galleryImageSerializer)
    {
        // todo probablement pagination !
        return $this->json($galleryImageSerializer->toList($gallery->getImages()));
    }
}
