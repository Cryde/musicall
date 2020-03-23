<?php

namespace App\Controller\Api;

use App\Entity\Gallery;
use App\Entity\Image\GalleryImage;
use App\Entity\User;
use App\Form\ImageUploaderType;
use App\Repository\GalleryRepository;
use App\Serializer\UserGalleryImageSerializer;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

class UserGalleryController extends AbstractController
{
    /**
     * @Route("/api/user/gallery", name="api_user_gallery_list", methods={"GET"}, options={"expose": true})
     *
     * @IsGranted("IS_AUTHENTICATED_REMEMBERED")
     *
     * @param GalleryRepository $galleryRepository
     *
     * @return JsonResponse
     */
    public function list(GalleryRepository $galleryRepository)
    {
        $galleries = $galleryRepository->findBy(['author' => $this->getUser()], ['creationDatetime' => 'DESC']);

        return $this->json($galleries, Response::HTTP_OK, [], [
            AbstractNormalizer::IGNORED_ATTRIBUTES => ['author', 'images']
        ]);
    }
    /**
     * @Route("/api/user/gallery", name="api_user_gallery_add", methods={"POST"}, options={"expose": true})
     *
     * @IsGranted("IS_AUTHENTICATED_REMEMBERED")
     *
     * @param Request             $request
     * @param SerializerInterface $serializer
     * @param ValidatorInterface  $validator
     *
     * @return JsonResponse
     */
    public function add(Request $request, SerializerInterface $serializer, ValidatorInterface $validator)
    {
        /** @var User $author */
        $author = $this->getUser();

        /** @var Gallery $gallery */
        $gallery = $serializer->deserialize($request->getContent(), Gallery::class, 'json');
        $gallery->setAuthor($author);

        $errors = $validator->validate($gallery);
        if (count($errors) > 0) {
            return $this->json(['data' => ['errors' => $errors]], Response::HTTP_UNAUTHORIZED);
        }

        $this->getDoctrine()->getManager()->persist($gallery);
        $this->getDoctrine()->getManager()->flush();

        return $this->json($gallery, Response::HTTP_CREATED, [], [
            AbstractNormalizer::ATTRIBUTES => ['id'],
        ]);
    }

    /**
     * @Route("/api/user/gallery/{id}", name="api_user_gallery_get", methods={"GET"}, options={"expose": true})
     *
     * @IsGranted("IS_AUTHENTICATED_REMEMBERED")
     *
     * @param Gallery $gallery
     *
     * @return JsonResponse
     */
    public function show(Gallery $gallery)
    {
        if ($this->getUser()->getId() !== $gallery->getAuthor()->getId()) {
            return $this->json(['data' => ['success' => 0, 'message' => 'Cette galerie ne vous appartient pas']], Response::HTTP_FORBIDDEN);
        }

        return $this->json($gallery, Response::HTTP_OK, [], [
            AbstractNormalizer::IGNORED_ATTRIBUTES => ['author', 'images']
        ]);
    }

    /**
     * @Route("/api/user/gallery/{id}/images", name="api_user_gallery_images", methods={"GET"}, options={"expose": true})
     *
     * @IsGranted("IS_AUTHENTICATED_REMEMBERED")
     *
     * @param Gallery                    $gallery
     * @param UserGalleryImageSerializer $userGalleryImageSerializer
     *
     * @return JsonResponse
     */
    public function images(Gallery $gallery, UserGalleryImageSerializer $userGalleryImageSerializer)
    {
        if ($this->getUser()->getId() !== $gallery->getAuthor()->getId()) {
            return $this->json(['data' => ['success' => 0, 'message' => 'Cette galerie ne vous appartient pas']], Response::HTTP_FORBIDDEN);
        }

        return $this->json($userGalleryImageSerializer->toList($gallery->getImages()));
    }

    /**
     * @Route("/api/user/gallery/{id}/upload-image", name="api_user_gallery_upload_image", options={"expose": true}, methods={"POST"})
     *
     * @IsGranted("IS_AUTHENTICATED_REMEMBERED")
     *
     * @param Request                    $request
     * @param Gallery                    $gallery
     * @param UserGalleryImageSerializer $userGalleryImageSerializer
     *
     * @return JsonResponse
     */
    public function uploadImage(
        Request $request,
        Gallery $gallery,
        UserGalleryImageSerializer $userGalleryImageSerializer
    ) {
        if ($this->getUser()->getId() !== $gallery->getAuthor()->getId()) {
            return $this->json(['data' => ['success' => 0, 'message' => 'Cette galerie ne vous appartient pas']], Response::HTTP_FORBIDDEN);
        }

        $image = new GalleryImage();
        $image->setGallery($gallery);
        $form = $this->createForm(ImageUploaderType::class, $image);

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->persist($image);
            $this->getDoctrine()->getManager()->flush();

            return $this->json($userGalleryImageSerializer->toArray($image));
        }

        return $this->json(['error' => $form->getErrors(true, true)]);
    }

    /**
     * @Route("/api/user/gallery/image/{id}", name="api_user_gallery_image_delete", options={"expose": true}, methods={"DELETE"})
     *
     * @IsGranted("IS_AUTHENTICATED_REMEMBERED")
     *
     * @param GalleryImage $galleryImage
     *
     * @return JsonResponse
     */
    public function removeImage(GalleryImage $galleryImage)
    {
        if ($this->getUser()->getId() !== $galleryImage->getGallery()->getAuthor()->getId()) {
            return $this->json(['data' => ['success' => 0, 'message' => 'Cette galerie ne vous appartient pas']], Response::HTTP_FORBIDDEN);
        }

        // todo : vérifier que l'image n'est pas une cover image de la galerie !

        $this->getDoctrine()->getManager()->remove($galleryImage);
        $this->getDoctrine()->getManager()->flush();

        return $this->json([], Response::HTTP_OK);
    }
}
