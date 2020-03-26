<?php

namespace App\Controller\Api;

use App\Entity\Gallery;
use App\Serializer\GalleryImageSerializer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

class GalleryController extends AbstractController
{
    /**
     * @Route("/api/gallery", name="api_gallery", options={"expose": true})
     */
    public function list()
    {
        return $this->json([]);
    }

    /**
     * @Route("/api/gallery/{slug}", name="api_gallery_show", options={"expose": true})
     *
     * @param Gallery $gallery
     *
     * @return JsonResponse
     */
    public function show(Gallery $gallery)
    {
        return $this->json($gallery, Response::HTTP_OK, [], [
            AbstractNormalizer::ATTRIBUTES => [
                'author' => ['username'],
                'title',
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
