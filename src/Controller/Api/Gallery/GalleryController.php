<?php

namespace App\Controller\Api\Gallery;

use App\Entity\Gallery;
use App\Entity\User;
use App\Serializer\GalleryImageSerializer;
use App\Service\Procedure\Metric\ViewProcedure;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

/** @method User getUser() */
class GalleryController extends AbstractController
{
    #[Route(path: '/api/gallery/{slug}/images', name: 'api_gallery_images_show', options: ['expose' => true])]
    public function images(Gallery $gallery, GalleryImageSerializer $galleryImageSerializer): JsonResponse
    {
        // todo probablement pagination !
        return $this->json($galleryImageSerializer->toList($gallery->getImages()));
    }
}
