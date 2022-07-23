<?php

namespace App\Controller\Api\Admin;

use App\Entity\Image\WikiArtistCover;
use App\Entity\Wiki\Artist;
use App\Form\ImageUploaderType;
use App\Repository\Wiki\ArtistRepository;
use App\Serializer\Artist\AdminArtistArraySerializer;
use App\Service\Builder\Wiki\ArtistDirector;
use App\Service\Jsonizer;
use App\Service\Slugifier;
use App\Service\Updater\Wiki\ArtistUpdater;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class WikiArtistController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route(path: '/api/admin/artist', name: 'api_admin_artist_add', options: ['expose' => true], methods: ['POST'])]
    public function add(
        Request                    $request,
        SerializerInterface        $serializer,
        ValidatorInterface         $validator,
        Slugifier                  $slugifier,
        AdminArtistArraySerializer $adminArtistArraySerializer
    ): JsonResponse {
        /** @var Artist $artist */
        $artist = $serializer->deserialize($request->getContent(), Artist::class, 'json');
        $artist->setSlug($slugifier->create($artist, 'name'));
        $errors = $validator->validate($artist);
        if (count($errors) > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }
        $this->entityManager->persist($artist);
        $this->entityManager->flush();

        return $this->json($adminArtistArraySerializer->toArray($artist));
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route(path: '/api/admin/artist/{id}', name: 'api_admin_artist_edit', options: ['expose' => true], methods: ['PATCH'])]
    public function edit(
        Artist             $artist,
        Request            $request,
        ArtistDirector     $artistDirector,
        ArtistUpdater      $artistUpdater,
        Jsonizer           $jsonizer,
        ValidatorInterface $validator
    ): JsonResponse {
        $newArtist = $artistDirector->createFromArray($jsonizer->decodeRequest($request));
        $artistUpdater->update($artist, $newArtist);
        $errors = $validator->validate($artist);
        if (count($errors) > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }
        $this->entityManager->flush();

        return $this->json([]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route(path: '/api/admin/artist', name: 'api_admin_artist_list', options: ['expose' => true], methods: ['GET'])]
    public function list(
        ArtistRepository           $wikiArtistRepository,
        AdminArtistArraySerializer $adminArtistArraySerializer
    ): JsonResponse {
        return $this->json($adminArtistArraySerializer->listToArray($wikiArtistRepository->findAll()));
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route(path: '/api/admin/artist/{id}', name: 'api_admin_artist_show', options: ['expose' => true], methods: ['GET'])]
    public function show(Artist $artist, AdminArtistArraySerializer $adminArtistArraySerializer): JsonResponse
    {
        return $this->json($adminArtistArraySerializer->toArray($artist));
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route(path: '/api/admin/artist/{id}/upload-cover', name: 'api_admin_artist_upload_cover', options: ['expose' => true], methods: ['POST'])]
    public function uploadCover(
        Request                    $request,
        Artist                     $artist,
        AdminArtistArraySerializer $adminArtistArraySerializer
    ): JsonResponse {
        $previousCover = $artist->getCover() ?: null;
        $cover = new WikiArtistCover();
        $cover->setArtist($artist);
        $form = $this->createForm(ImageUploaderType::class, $cover);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($previousCover) {
                $artist->setCover(null);
                $this->entityManager->flush();
                $this->entityManager->remove($previousCover);
                $this->entityManager->flush();
            }
            $this->entityManager->persist($cover);
            $artist->setCover($cover);
            $this->entityManager->flush();

            return $this->json($adminArtistArraySerializer->toArray($artist));
        }

        return $this->json($form->getErrors(true, true), Response::HTTP_BAD_REQUEST);
    }
}
