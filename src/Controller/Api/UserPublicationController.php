<?php

namespace App\Controller\Api;

use App\Entity\Image\PublicationCover;
use App\Entity\Image\PublicationImage;
use App\Entity\Publication;
use App\Form\ImageUploaderType;
use App\Repository\PublicationRepository;
use App\Serializer\UserPublicationArraySerializer;
use App\Service\Jsonizer;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

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

    /**
     * @Route("/api/users/publications/{id}", name="api_user_publication_show", options={"expose": true})
     *
     * @IsGranted("IS_AUTHENTICATED_REMEMBERED")
     *
     * @param Publication                    $publication
     * @param UserPublicationArraySerializer $userPublicationArraySerializer
     *
     * @return object|JsonResponse
     */
    public function show(Publication $publication, UserPublicationArraySerializer $userPublicationArraySerializer)
    {
        if ($this->getUser()->getId() !== $publication->getAuthor()->getId()) {
            return $this->json(['data' => ['success' => 0, 'message' => 'Ce publication ne vous appartient pas']], Response::HTTP_FORBIDDEN);
        }

        return $this->json(['data' => ['publication' => $userPublicationArraySerializer->toArray($publication, true)]]);
    }

    /**
     * @Route("/api/users/publications/{id}/save", name="api_user_publication_save", options={"expose": true}, methods={"POST"})
     *
     * @IsGranted("IS_AUTHENTICATED_REMEMBERED")
     *
     * @param Publication        $publication
     * @param Request            $request
     * @param Jsonizer           $jsonizer
     * @param ValidatorInterface $validator
     *
     * @return JsonResponse
     */
    public function save(Publication $publication, Request $request, Jsonizer $jsonizer, ValidatorInterface $validator)
    {
        if ($this->getUser()->getId() !== $publication->getAuthor()->getId()) {
            return $this->json(['data' => ['success' => 0, 'message' => 'Ce publication ne vous appartient pas']], Response::HTTP_FORBIDDEN);
        }

        $data = $jsonizer->decodeRequest($request);

        $publication->setTitle($data['title']);
        $publication->setShortDescription($data['short_description']);
        $publication->setContent($data['content']);

        $errors = $validator->validate($publication);

        if (count($errors) > 0) {
            return $this->json(['data' => ['errors' => $errors]], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $this->getDoctrine()->getManager()->flush();

        return $this->json(['data' => ['success' => 1]]);
    }

    /**
     * @Route("/api/users/publications/{id}/upload-image", name="api_user_publication_upload_image", options={"expose": true}, methods={"POST"})
     *
     * @IsGranted("IS_AUTHENTICATED_REMEMBERED")
     *
     * @param Request        $request
     * @param Publication    $publication
     * @param UploaderHelper $uploaderHelper
     * @param CacheManager   $cacheManager
     *
     * @return JsonResponse
     */
    public function uploadImage(Request $request, Publication $publication, UploaderHelper $uploaderHelper, CacheManager $cacheManager)
    {
        if ($this->getUser()->getId() !== $publication->getAuthor()->getId()) {
            return $this->json(['data' => ['success' => 0, 'message' => 'Ce publication ne vous appartient pas']], Response::HTTP_FORBIDDEN);
        }

        $image = new PublicationImage();
        $image->setPublication($publication);
        $form = $this->createForm(ImageUploaderType::class, $image);

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->persist($image);
            $this->getDoctrine()->getManager()->flush();

            $imagePath = $uploaderHelper->asset($image, 'imageFile');

            return $this->json(['data' => ['uri' => $cacheManager->generateUrl($imagePath, 'publication_image_filter')]]);
        }

        return $this->json(['error' => 1]);
    }

    /**
     * @Route("/api/users/publications/{id}/upload-cover", name="api_user_publication_upload_cover", options={"expose": true}, methods={"POST"})
     *
     * @IsGranted("IS_AUTHENTICATED_REMEMBERED")
     *
     * @param Request        $request
     * @param Publication    $publication
     * @param UploaderHelper $uploaderHelper
     * @param CacheManager   $cacheManager
     *
     * @return JsonResponse
     */
    public function uploadCover(Request $request, Publication $publication, UploaderHelper $uploaderHelper, CacheManager $cacheManager)
    {
        if ($this->getUser()->getId() !== $publication->getAuthor()->getId()) {
            return $this->json(['data' => ['success' => 0, 'message' => 'Ce publication ne vous appartient pas']], Response::HTTP_FORBIDDEN);
        }

        $previousCover = $publication->getCover() ? $publication->getCover() : null;

        $cover = new PublicationCover();
        $cover->setPublication($publication);
        $form = $this->createForm(ImageUploaderType::class, $cover);

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {

            if($previousCover) {
                $publication->setCover(null);
                $this->getDoctrine()->getManager()->flush();
                $this->getDoctrine()->getManager()->remove($previousCover);
                $this->getDoctrine()->getManager()->flush();
            }

            $this->getDoctrine()->getManager()->persist($cover);
            $publication->setCover($cover);
            $this->getDoctrine()->getManager()->flush();

            $imagePath = $uploaderHelper->asset($cover, 'imageFile');

            return $this->json(['data' => ['uri' => $cacheManager->generateUrl($imagePath, 'publication_cover_300x300')]]);
        }

        return $this->json(['error' => 1]);
    }
}
