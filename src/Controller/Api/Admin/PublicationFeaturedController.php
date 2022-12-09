<?php

namespace App\Controller\Api\Admin;

use App\Entity\Image\PublicationFeaturedImage;
use App\Entity\Publication;
use App\Entity\PublicationFeatured;
use App\Form\ImageUploaderType;
use App\Repository\PublicationFeaturedRepository;
use App\Serializer\PublicationFeaturedSerializer;
use Doctrine\ORM\EntityManagerInterface;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Component\Security\Http\Attribute\IsGranted;
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
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route(path: '/api/admin/publication/{id}/featured/add', name: 'api_admin_publication_featured_add', options: ['expose' => true], methods: ['POST'])]
    public function add(
        Publication                   $publication,
        Request                       $request,
        SerializerInterface           $serializer,
        ValidatorInterface            $validator,
        PublicationFeaturedSerializer $publicationFeaturedSerializer
    ): JsonResponse {
        /** @var PublicationFeatured $publicationFeatured */
        $publicationFeatured = $serializer->deserialize($request->getContent(), PublicationFeatured::class, 'json');
        $publicationFeatured->setPublication($publication);
        $errors = $validator->validate($publicationFeatured, null, ['add']);
        if (count($errors) > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }
        $this->entityManager->persist($publicationFeatured);
        $this->entityManager->flush();

        return $this->json($publicationFeaturedSerializer->toArray($publicationFeatured));
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route(path: '/api/admin/publication/featured/{id}', name: 'api_admin_publication_featured_edit', options: ['expose' => true], methods: ['POST'])]
    public function edit(
        PublicationFeatured           $publicationFeatured,
        Request                       $request,
        SerializerInterface           $serializer,
        ValidatorInterface            $validator,
        PublicationFeaturedSerializer $publicationFeaturedSerializer
    ): JsonResponse {
        /** @var PublicationFeatured $publicationFeatured */
        $publicationFeatured = $serializer->deserialize($request->getContent(), PublicationFeatured::class, 'json', [
            AbstractNormalizer::OBJECT_TO_POPULATE => $publicationFeatured,
        ]);
        $errors = $validator->validate($publicationFeatured, null, ['edit']);
        if (count($errors) > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }
        $this->entityManager->flush();

        return $this->json($publicationFeaturedSerializer->toArray($publicationFeatured));
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route(path: '/api/admin/publication/featured/{id}/options', name: 'api_admin_publication_featured_options', options: ['expose' => true], methods: ['PATCH'])]
    public function option(
        PublicationFeatured           $publicationFeatured,
        Request                       $request,
        PublicationFeaturedSerializer $publicationFeaturedSerializer
    ): JsonResponse {
        $backgroundSet = in_array($request->get('color', ''), ['dark', 'light']) ? $request->get('color', '') : 'dark';
        $options = $publicationFeatured->getOptions();
        $options['color'] = $backgroundSet;
        $publicationFeatured->setOptions($options);
        $this->entityManager->flush();

        return $this->json($publicationFeaturedSerializer->toArray($publicationFeatured));
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route(path: '/api/admin/publication/featured/{id}/cover', name: 'api_admin_publication_featured_cover', options: ['expose' => true], methods: ['POST'])]
    public function uploadImage(
        Request             $request,
        PublicationFeatured $publicationFeatured,
        UploaderHelper      $uploaderHelper,
        CacheManager        $cacheManager
    ): JsonResponse {
        $previousFeaturedCover = $publicationFeatured->getCover() ?: null;
        $cover = new PublicationFeaturedImage();
        $cover->setPublicationFeatured($publicationFeatured);
        $form = $this->createForm(ImageUploaderType::class, $cover);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($previousFeaturedCover) {
                $publicationFeatured->setCover(null);
                $this->entityManager->flush();
                $this->entityManager->remove($previousFeaturedCover);
                $this->entityManager->flush();
            }
            $this->entityManager->persist($cover);
            $publicationFeatured->setCover($cover);
            $this->entityManager->flush();
            $imagePath = $uploaderHelper->asset($cover, 'imageFile');

            return $this->json(['data' => ['uri' => $cacheManager->getBrowserPath($imagePath, 'featured_cover_filter')]]);
        }

        return $this->json($form->getErrors(true, true), Response::HTTP_BAD_REQUEST);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route(path: '/api/admin/publication/featured/{id}', name: 'api_admin_publication_featured_delete', options: ['expose' => true], methods: ['DELETE'])]
    public function remove(PublicationFeatured $publicationFeatured): JsonResponse
    {
        if ($cover = $publicationFeatured->getCover()) {
            $publicationFeatured->setCover(null);
            $this->entityManager->flush();
            $this->entityManager->remove($cover);
            $this->entityManager->flush();
        }
        $this->entityManager->remove($publicationFeatured);
        $this->entityManager->flush();

        return $this->json([], Response::HTTP_NO_CONTENT);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route(path: '/api/admin/publication/featured/{id}/publish', name: 'api_admin_publication_featured_publish', options: ['expose' => true], methods: ['PATCH'])]
    public function publish(
        PublicationFeatured           $publicationFeatured,
        PublicationFeaturedSerializer $publicationFeaturedSerializer,
        ValidatorInterface            $validator
    ): JsonResponse {
        $publicationFeatured->setStatus(PublicationFeatured::STATUS_ONLINE);
        $errors = $validator->validate($publicationFeatured, null, ['publish']);
        if (count($errors) > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }
        $this->entityManager->flush();

        return $this->json($publicationFeaturedSerializer->toArray($publicationFeatured));
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route(path: '/api/admin/publication/featured/{id}/unpublish', name: 'api_admin_publication_featured_unpublish', options: ['expose' => true], methods: ['PATCH'])]
    public function unpublish(
        PublicationFeatured           $publicationFeatured,
        PublicationFeaturedSerializer $publicationFeaturedSerializer
    ): JsonResponse {
        $publicationFeatured->setStatus(PublicationFeatured::STATUS_DRAFT);
        $this->entityManager->flush();

        return $this->json($publicationFeaturedSerializer->toArray($publicationFeatured));
    }
}
