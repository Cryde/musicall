<?php

namespace App\Controller\Api\Admin;

use App\Entity\Image\PublicationFeaturedImage;
use App\Entity\Publication;
use App\Entity\PublicationFeatured;
use App\Form\ImageUploaderType;
use App\Repository\PublicationFeaturedRepository;
use App\Serializer\PublicationFeaturedSerializer;
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

class PublicationFeaturedController extends AbstractController
{
    /**
     * @Route(
     *     "/api/admin/publication/featured",
     *     name="api_admin_publication_featured_list",
     *     options={"expose": true}
     * )
     *
     * @IsGranted("ROLE_ADMIN")
     *
     * @param PublicationFeaturedRepository $publicationFeaturedRepository
     * @param PublicationFeaturedSerializer $publicationFeaturedSerializer
     *
     * @return JsonResponse
     */
    public function list(
        PublicationFeaturedRepository $publicationFeaturedRepository,
        PublicationFeaturedSerializer $publicationFeaturedSerializer
    ) {
        return $this->json($publicationFeaturedSerializer->toList($publicationFeaturedRepository->findAll()));
    }

    /**
     * @Route(
     *     "/api/admin/publication/{id}/featured/add",
     *     name="api_admin_publication_featured_add",
     *     methods={"POST"},
     *     options={"expose": true}
     * )
     *
     * @param Publication                   $publication
     * @param Request                       $request
     * @param SerializerInterface           $serializer
     * @param ValidatorInterface            $validator
     * @param PublicationFeaturedSerializer $publicationFeaturedSerializer
     *
     * @return JsonResponse
     */
    public function add(
        Publication $publication,
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        PublicationFeaturedSerializer $publicationFeaturedSerializer
    ) {
        /** @var PublicationFeatured $publicationFeatured */
        $publicationFeatured = $serializer->deserialize($request->getContent(), PublicationFeatured::class, 'json');
        $publicationFeatured->setPublication($publication);

        $errors = $validator->validate($publicationFeatured, null, ['add']);

        if (count($errors) > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $this->getDoctrine()->getManager()->persist($publicationFeatured);
        $this->getDoctrine()->getManager()->flush();

        return $this->json($publicationFeaturedSerializer->toArray($publicationFeatured));
    }

    /**
     * @Route(
     *     "/api/admin/publication/featured/{id}",
     *     name="api_admin_publication_featured_edit",
     *     methods={"POST"},
     *     options={"expose": true}
     * )
     *
     * @IsGranted("ROLE_ADMIN")
     *
     * @param PublicationFeatured           $publicationFeatured
     * @param Request                       $request
     * @param SerializerInterface           $serializer
     * @param ValidatorInterface            $validator
     * @param PublicationFeaturedSerializer $publicationFeaturedSerializer
     *
     * @return JsonResponse
     */
    public function edit(
        PublicationFeatured $publicationFeatured,
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        PublicationFeaturedSerializer $publicationFeaturedSerializer
    ) {
        /** @var PublicationFeatured $publicationFeatured */
        $publicationFeatured = $serializer->deserialize($request->getContent(), PublicationFeatured::class, 'json', [
            AbstractNormalizer::OBJECT_TO_POPULATE => $publicationFeatured,
        ]);
        $errors = $validator->validate($publicationFeatured, null, ['edit']);

        if (count($errors) > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $this->getDoctrine()->getManager()->flush();

        return $this->json($publicationFeaturedSerializer->toArray($publicationFeatured));
    }

    /**
     * @Route(
     *     "/api/admin/publication/featured/{id}/options",
     *     name="api_admin_publication_featured_options",
     *     methods={"PATCH"},
     *     options={"expose": true}
     * )
     *
     * @IsGranted("ROLE_ADMIN")
     *
     * @param PublicationFeatured           $publicationFeatured
     * @param Request                       $request
     * @param PublicationFeaturedSerializer $publicationFeaturedSerializer
     *
     * @return JsonResponse
     */
    public function option(
        PublicationFeatured $publicationFeatured,
        Request $request,
        PublicationFeaturedSerializer $publicationFeaturedSerializer
    ) {
        $backgroundSet = in_array($request->get('color', ''), ['dark', 'light']) ? $request->get('color', '') : 'dark';

        $options = $publicationFeatured->getOptions();
        $options['color'] = $backgroundSet;

        $publicationFeatured->setOptions($options);
        $this->getDoctrine()->getManager()->flush();

        return $this->json($publicationFeaturedSerializer->toArray($publicationFeatured));
    }

    /**
     * @Route(
     *     "/api/admin/publication/featured/{id}",
     *     name="api_admin_publication_featured_delete",
     *     methods={"DELETE"},
     *     options={"expose": true}
     * )
     *
     * @IsGranted("ROLE_ADMIN")
     *
     * @param PublicationFeatured $publicationFeatured
     *
     * @return JsonResponse
     */
    public function remove(PublicationFeatured $publicationFeatured)
    {
        if($cover = $publicationFeatured->getCover()) {
            $publicationFeatured->setCover(null);
            $this->getDoctrine()->getManager()->flush();
            $this->getDoctrine()->getManager()->remove($cover);
            $this->getDoctrine()->getManager()->flush();
        }

        $this->getDoctrine()->getManager()->remove($publicationFeatured);
        $this->getDoctrine()->getManager()->flush();

        return $this->json([], Response::HTTP_NO_CONTENT);
    }

    /**
     * @Route(
     *     "/api/admin/publication/featured/{id}/cover",
     *     name="api_admin_publication_featured_cover",
     *     options={"expose": true},
     *     methods={"POST"}
     * )
     *
     * @IsGranted("ROLE_ADMIN")
     *
     * @param Request             $request
     * @param PublicationFeatured $publicationFeatured
     * @param UploaderHelper      $uploaderHelper
     * @param CacheManager        $cacheManager
     *
     * @return JsonResponse
     */
    public function uploadImage(
        Request $request,
        PublicationFeatured $publicationFeatured,
        UploaderHelper $uploaderHelper,
        CacheManager $cacheManager
    ) {
        $previousFeaturedCover = $publicationFeatured->getCover() ? $publicationFeatured->getCover() : null;

        $cover = new PublicationFeaturedImage();
        $cover->setPublicationFeatured($publicationFeatured);
        $form = $this->createForm(ImageUploaderType::class, $cover);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            if ($previousFeaturedCover) {
                $publicationFeatured->setCover(null);
                $this->getDoctrine()->getManager()->flush();
                $this->getDoctrine()->getManager()->remove($previousFeaturedCover);
                $this->getDoctrine()->getManager()->flush();
            }

            $this->getDoctrine()->getManager()->persist($cover);
            $publicationFeatured->setCover($cover);
            $this->getDoctrine()->getManager()->flush();
            $imagePath = $uploaderHelper->asset($cover, 'imageFile');

            return $this->json(['data' => ['uri' => $cacheManager->generateUrl($imagePath, 'featured_cover_filter')]]);
        }

        return $this->json($form->getErrors(true, true), Response::HTTP_BAD_REQUEST);
    }

    /**
     * @Route(
     *     "/api/admin/publication/featured/{id}/publish",
     *     name="api_admin_publication_featured_publish",
     *     methods={"PATCH"},
     *     options={"expose": true}
     * )
     *
     * @IsGranted("ROLE_ADMIN")
     *
     * @param PublicationFeatured           $publicationFeatured
     * @param PublicationFeaturedSerializer $publicationFeaturedSerializer
     * @param ValidatorInterface            $validator
     *
     * @return JsonResponse
     */
    public function publish(
        PublicationFeatured $publicationFeatured,
        PublicationFeaturedSerializer $publicationFeaturedSerializer,
        ValidatorInterface $validator
    ) {
        $publicationFeatured->setStatus(PublicationFeatured::STATUS_ONLINE);
        $errors = $validator->validate($publicationFeatured, null, ['publish']);

        if (count($errors) > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $this->getDoctrine()->getManager()->flush();

        return $this->json($publicationFeaturedSerializer->toArray($publicationFeatured));
    }

    /**
     * @Route(
     *     "/api/admin/publication/featured/{id}/unpublish",
     *     name="api_admin_publication_featured_unpublish",
     *     methods={"PATCH"},
     *     options={"expose": true}
     * )
     *
     * @IsGranted("ROLE_ADMIN")
     *
     * @param PublicationFeatured           $publicationFeatured
     * @param PublicationFeaturedSerializer $publicationFeaturedSerializer
     *
     * @return JsonResponse
     */
    public function unpublish(
        PublicationFeatured $publicationFeatured,
        PublicationFeaturedSerializer $publicationFeaturedSerializer
    ) {
        $publicationFeatured->setStatus(PublicationFeatured::STATUS_DRAFT);
        $this->getDoctrine()->getManager()->flush();

        return $this->json($publicationFeaturedSerializer->toArray($publicationFeatured));
    }
}
