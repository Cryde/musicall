<?php

namespace App\Controller\Api\Admin;

use App\Entity\Wiki\Artist;
use App\Repository\Wiki\ArtistRepository;
use App\Repository\WikiArtistRepository;
use App\Service\Slugifier;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
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
     *     "/api/admin/artist",
     *     name="api_admin_artist_list",
     *     options={"expose": true},
     *     methods={"GET"},
     * )
     *
     * @IsGranted("ROLE_ADMIN")
     *
     * @param ArtistRepository $wikiArtistRepository
     *
     * @return JsonResponse
     */
    public function list(ArtistRepository $wikiArtistRepository) {

        return $this->json($wikiArtistRepository->findAll(), Response::HTTP_OK, [], [
            AbstractNormalizer::ATTRIBUTES => ['id', 'name']
        ]);
    }
}
