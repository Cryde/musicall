<?php

namespace App\Controller\Api\User;

use App\Entity\Gallery;
use App\Entity\Image\GalleryImage;
use App\Entity\User;
use App\Form\ImageUploaderType;
use App\Repository\GalleryRepository;
use App\Serializer\GalleryImageSerializer;
use App\Serializer\Normalizer\UserGalleryNormalizer;
use App\Service\Publication\GallerySlug;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

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
            AbstractNormalizer::IGNORED_ATTRIBUTES => ['author', 'images', 'coverImage']
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
        $gallery->setSlug('gallery-'.uniqid('', true));

        $errors = $validator->validate($gallery);
        if (count($errors) > 0) {
            return $this->json(['data' => ['errors' => $errors]], Response::HTTP_UNAUTHORIZED);
        }

        $this->getDoctrine()->getManager()->persist($gallery);
        $this->getDoctrine()->getManager()->flush();

        return $this->json($gallery, Response::HTTP_CREATED, [], [
            UserGalleryNormalizer::CONTEXT_USER_GALLERY => true,
        ]);
    }

    /**
     * @Route("/api/user/gallery/{id}", name="api_user_gallery_edit", methods={"POST"}, options={"expose": true})
     *
     * @IsGranted("IS_AUTHENTICATED_REMEMBERED")
     *
     * @param Gallery             $gallery
     * @param Request             $request
     * @param SerializerInterface $serializer
     * @param ValidatorInterface  $validator
     *
     * @return JsonResponse
     * @throws \Exception
     */
    public function edit(Gallery $gallery, Request $request, SerializerInterface $serializer, ValidatorInterface $validator)
    {
        if ($this->getUser()->getId() !== $gallery->getAuthor()->getId()) {
            return $this->json(['data' => ['success' => 0, 'message' => 'Cette galerie ne vous appartient pas']], Response::HTTP_FORBIDDEN);
        }

        if ($gallery->getStatus() !== Gallery::STATUS_DRAFT) {
            return $this->json(['data' => ['success' => 0, 'message' => 'Vous ne pouvez pas modifier de galerie en ligne ou en validation']], Response::HTTP_FORBIDDEN);
        }

        /** @var Gallery $gallery */
        $gallery = $serializer->deserialize($request->getContent(), Gallery::class, 'json', [AbstractNormalizer::OBJECT_TO_POPULATE => $gallery]);

        $errors = $validator->validate($gallery);
        if (count($errors) > 0) {
            return $this->json(['data' => ['errors' => $errors]], Response::HTTP_UNAUTHORIZED);
        }

        $gallery->setUpdateDatetime(new \DateTime());
        $this->getDoctrine()->getManager()->flush();

        return $this->json($gallery, Response::HTTP_OK, [], [
            UserGalleryNormalizer::CONTEXT_USER_GALLERY => true,
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
            UserGalleryNormalizer::CONTEXT_USER_GALLERY => true,
        ]);
    }

    /**
     * @Route("/api/user/gallery/{id}/images", name="api_user_gallery_images", methods={"GET"}, options={"expose": true})
     *
     * @IsGranted("IS_AUTHENTICATED_REMEMBERED")
     *
     * @param Gallery                $gallery
     * @param GalleryImageSerializer $userGalleryImageSerializer
     *
     * @return JsonResponse
     */
    public function images(Gallery $gallery, GalleryImageSerializer $userGalleryImageSerializer)
    {
        if ($this->getUser()->getId() !== $gallery->getAuthor()->getId()) {
            return $this->json(['data' => ['success' => 0, 'message' => 'Cette galerie ne vous appartient pas']], Response::HTTP_FORBIDDEN);
        }

        return $this->json($userGalleryImageSerializer->toList($gallery->getImages()));
    }

    /**
     * @Route("/api/user/gallery/{id}/validation", name="api_user_gallery_validation", methods={"PATCH"}, options={"expose": true})
     *
     * @IsGranted("IS_AUTHENTICATED_REMEMBERED")
     *
     * @param Gallery            $gallery
     * @param ValidatorInterface $validator
     *
     * @return JsonResponse
     * @throws \Exception
     */
    public function validation(Gallery $gallery, ValidatorInterface $validator)
    {
        if ($this->getUser()->getId() !== $gallery->getAuthor()->getId()) {
            return $this->json(['data' => ['success' => 0, 'message' => 'Cette galerie ne vous appartient pas']], Response::HTTP_FORBIDDEN);
        }

        if ($gallery->getStatus() !== Gallery::STATUS_DRAFT) {
            return $this->json(['data' => ['success' => 0, 'message' => 'Cette galerie est déjà en ligne ou en validation']], Response::HTTP_FORBIDDEN);
        }

        $errors = $validator->validate($gallery, null, ['publish']);
        if (count($errors) > 0) {
            return $this->json($errors, Response::HTTP_UNAUTHORIZED);
        }

        $gallery->setStatus(Gallery::STATUS_PENDING);

        $this->getDoctrine()->getManager()->flush();

        return $this->json($gallery, Response::HTTP_OK, [], [
            UserGalleryNormalizer::CONTEXT_USER_GALLERY => true,
        ]);
    }


    /**
     * @Route("/api/user/gallery/{id}/upload-image", name="api_user_gallery_upload_image", options={"expose": true}, methods={"POST"})
     *
     * @IsGranted("IS_AUTHENTICATED_REMEMBERED")
     *
     * @param Request                $request
     * @param Gallery                $gallery
     * @param GalleryImageSerializer $userGalleryImageSerializer
     *
     * @return JsonResponse
     */
    public function uploadImage(
        Request $request,
        Gallery $gallery,
        GalleryImageSerializer $userGalleryImageSerializer
    ) {
        if ($this->getUser()->getId() !== $gallery->getAuthor()->getId()) {
            return $this->json(['data' => ['success' => 0, 'message' => 'Cette galerie ne vous appartient pas']], Response::HTTP_FORBIDDEN);
        }

        if ($gallery->getStatus() !== Gallery::STATUS_DRAFT) {
            return $this->json(['data' => ['success' => 0, 'message' => 'Vous ne pouvez pas modifier de galerie en ligne ou en validation']], Response::HTTP_FORBIDDEN);
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
        $gallery = $galleryImage->getGallery();
        if ($this->getUser()->getId() !== $gallery->getAuthor()->getId()) {
            return $this->json(['data' => ['success' => 0, 'message' => 'Cette galerie ne vous appartient pas']], Response::HTTP_FORBIDDEN);
        }

        if ($gallery->getStatus() !== Gallery::STATUS_DRAFT) {
            return $this->json(['data' => ['success' => 0, 'message' => 'Vous ne pouvez pas modifier de galerie en ligne ou en validation']], Response::HTTP_FORBIDDEN);
        }

        $cover = $gallery->getCoverImage();
        if ($cover && $cover->getId() === $galleryImage->getId()) {
            return $this->json(['data' => ['success' => 0, 'message' => 'Vous ne pouvez pas supprimer cette image car c\'est la couverture de la galerie']], Response::HTTP_FORBIDDEN);
        }

        $this->getDoctrine()->getManager()->remove($galleryImage);
        $this->getDoctrine()->getManager()->flush();

        return $this->json([], Response::HTTP_OK);
    }

    /**
     * @Route("/api/user/gallery/image/{id}/cover", name="api_user_gallery_image_cover", methods={"PATCH"}, options={"expose": true})
     *
     * @param GalleryImage $image
     *
     * @return JsonResponse
     * @throws \Exception
     */
    public function coverImage(GalleryImage $image)
    {
        $gallery = $image->getGallery();

        if ($this->getUser()->getId() !== $gallery->getAuthor()->getId()) {
            return $this->json(['data' => ['success' => 0, 'message' => 'Cette galerie ne vous appartient pas']], Response::HTTP_FORBIDDEN);
        }

        if ($gallery->getStatus() !== Gallery::STATUS_DRAFT) {
            return $this->json(['data' => ['success' => 0, 'message' => 'Vous ne pouvez pas modifier de galerie en ligne ou en validation']], Response::HTTP_FORBIDDEN);
        }

        $cover = $gallery->getCoverImage();

        if ($cover && $image->getId() === $cover->getId()) {
            $gallery->setCoverImage(null);
        } else {
            $gallery->setCoverImage($image);
        }

        $gallery->setUpdateDatetime(new \DateTime());
        $this->getDoctrine()->getManager()->flush();

        return $this->json($gallery, Response::HTTP_OK, [], [
            UserGalleryNormalizer::CONTEXT_USER_GALLERY => true,
        ]);
    }
}
