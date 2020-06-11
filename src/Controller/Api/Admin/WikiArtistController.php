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
    /**
     * @Route(
     *     "/api/admin/artist",
     *     name="api_admin_artist_add",
     *     options={"expose": true},
     *     methods={"POST"},
     * )
     *
     * @IsGranted("ROLE_ADMIN")
     *
     * @param Request             $request
     * @param SerializerInterface $serializer
     * @param ValidatorInterface  $validator
     * @param Slugifier           $slugifier
     *
     * @return JsonResponse
     */
    public function add(
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        Slugifier $slugifier
    ) {
        /** @var Artist $artist */
        $artist = $serializer->deserialize($request->getContent(), Artist::class, 'json');
        $artist->setSlug($slugifier->create($artist, 'name'));

        $errors = $validator->validate($artist);

        if (count($errors) > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $this->getDoctrine()->getManager()->persist($artist);
        $this->getDoctrine()->getManager()->flush();

        return $this->json([]);
    }

    /**
     * @Route(
     *     "/api/admin/artist/{id}",
     *     name="api_admin_artist_edit",
     *     options={"expose": true},
     *     methods={"PATCH"},
     * )
     *
     * @IsGranted("ROLE_ADMIN")
     *
     * @param Artist             $artist
     * @param Request            $request
     * @param ArtistDirector     $artistDirector
     * @param ArtistUpdater      $artistUpdater
     * @param Jsonizer           $jsonizer
     * @param ValidatorInterface $validator
     *
     * @return JsonResponse
     */
    public function edit(
        Artist $artist,
        Request $request,
        ArtistDirector $artistDirector,
        ArtistUpdater $artistUpdater,
        Jsonizer $jsonizer,
        ValidatorInterface $validator
    ) {
        $newArtist = $artistDirector->createFromArray($jsonizer->decodeRequest($request));
        $artistUpdater->update($artist, $newArtist);

        $errors = $validator->validate($artist);

        if (count($errors) > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $this->getDoctrine()->getManager()->flush();

        return $this->json([]);
    }

    /**
     * @Route(
     *     "/api/admin/artist",
     *     name="api_admin_artist_list",
     *     options={"expose": true},
     *     methods={"GET"},
     * )
     *
     * @IsGranted("ROLE_ADMIN")
     *
     * @param ArtistRepository           $wikiArtistRepository
     * @param AdminArtistArraySerializer $adminArtistArraySerializer
     *
     * @return JsonResponse
     */
    public function list(ArtistRepository $wikiArtistRepository, AdminArtistArraySerializer $adminArtistArraySerializer) {

        return $this->json($adminArtistArraySerializer->listToArray($wikiArtistRepository->findAll()));
    }

    /**
     * @Route(
     *     "/api/admin/artist/{id}",
     *     name="api_admin_artist_show",
     *     options={"expose": true},
     *     methods={"GET"},
     * )
     *
     * @IsGranted("ROLE_ADMIN")
     *
     * @param Artist                     $artist
     * @param AdminArtistArraySerializer $adminArtistArraySerializer
     *
     * @return JsonResponse
     */
    public function show(Artist $artist, AdminArtistArraySerializer $adminArtistArraySerializer)
    {
        return $this->json($adminArtistArraySerializer->toArray($artist));
    }

    /**
     * @Route(
     *     "/api/admin/artist/{id}/upload-cover",
     *     name="api_admin_artist_upload_cover",
     *     options={"expose" = true},
     *     methods={"POST"}
     * )
     *
     * @IsGranted("ROLE_ADMIN")
     *
     * @param Request                    $request
     * @param Artist                     $artist
     * @param AdminArtistArraySerializer $adminArtistArraySerializer
     *
     * @return JsonResponse
     */
    public function uploadCover(
        Request $request,
        Artist $artist,
        AdminArtistArraySerializer $adminArtistArraySerializer
    ) {
        $previousCover = $artist->getCover() ? $artist->getCover() : null;

        $cover = new WikiArtistCover();
        $cover->setArtist($artist);
        $form = $this->createForm(ImageUploaderType::class, $cover);

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {

            if ($previousCover) {
                $artist->setCover(null);
                $this->getDoctrine()->getManager()->flush();
                $this->getDoctrine()->getManager()->remove($previousCover);
                $this->getDoctrine()->getManager()->flush();
            }

            $this->getDoctrine()->getManager()->persist($cover);
            $artist->setCover($cover);
            $this->getDoctrine()->getManager()->flush();

            return $this->json($adminArtistArraySerializer->toArray($artist));
        }

        return $this->json($form->getErrors(true, true), Response::HTTP_BAD_REQUEST);
    }
}
