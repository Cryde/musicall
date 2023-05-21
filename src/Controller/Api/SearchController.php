<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Exception\Musician\InvalidFormatReturnedException;
use App\Exception\Musician\InvalidResultException;
use App\Exception\Musician\NoResultException;
use App\Model\Search\Musician;
use App\Model\Search\MusicianText;
use App\Repository\Musician\MusicianAnnounceRepository;
use App\Repository\PublicationRepository;
use App\Serializer\PublicationSerializer;
use App\Serializer\Search\MusicianSearchArraySerializer;
use App\Service\Finder\Musician\MusicianAIFinder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class SearchController extends AbstractController
{
    public function __construct(
        private readonly SerializerInterface           $serializer,
        private readonly ValidatorInterface            $validator,
        private readonly MusicianAIFinder              $musicianAIFinder,
        private readonly MusicianSearchArraySerializer $musicianSearchArraySerializer
    ) {
    }

    #[Route(path: '/api/search', name: 'api_search', options: ['expose' => true])]
    public function search(
        Request               $request,
        PublicationRepository $publicationRepository,
        PublicationSerializer $publicationSerializer
    ): JsonResponse {
        $term = $request->get('term', '');
        if (strlen((string)$term) < 3) {
            return $this->json([], Response::HTTP_NO_CONTENT);
        }
        $publications = $publicationRepository->getBySearchTerm($term);

        return $this->json($publicationSerializer->listToArray($publications));
    }

    #[Route(path: '/api/search/musician', name: 'api_search_musician', options: ['expose' => true], methods: ['POST'])]
    public function searchMusician(
        Request                    $request,
        MusicianAnnounceRepository $musicianAnnounceRepository,
    ): JsonResponse {
        /** @var Musician $musicianModel */
        $musicianModel = $this->serializer->deserialize($request->getContent(), Musician::class, 'json');
        /** @var User|null $user */
        $user = $this->getUser();
        $results = $musicianAnnounceRepository->findByCriteria($musicianModel, $user);
        $serializedData = $this->musicianSearchArraySerializer->listToArray($results);

        return $this->json(['data' => $serializedData]);
    }

    #[Route(path: '/api/search/musician/text', name: 'api_search_musician_text', options: ['expose' => true], methods: ['POST'])]
    public function searchMusicianText(Request $request): JsonResponse
    {
        /** @var MusicianText $musicianModelText */
        $musicianModelText = $this->serializer->deserialize($request->getContent(), MusicianText::class, 'json');
        $errors = $this->validator->validate($musicianModelText);
        if (count($errors) > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }
        /** @var User|null $user */
        $user = $this->getUser();
        try {
            $results = $this->musicianAIFinder->find($musicianModelText, $user);
        } catch (InvalidResultException $e) {
            return $this->json(['errors' => ['invalid_results']], Response::HTTP_BAD_REQUEST);
        } catch (NoResultException $e) {
            return $this->json(['errors' => ['no_results']], Response::HTTP_BAD_REQUEST);
        } catch (InvalidFormatReturnedException $e) {
            return $this->json(['errors' => ['invalid_format']], Response::HTTP_BAD_REQUEST);
        } catch(\Exception $e) {
            return $this->json(['errors' => ['unexpected_error']], Response::HTTP_BAD_REQUEST);
        }
        $serializedData = $this->musicianSearchArraySerializer->listToArray($results);

        return $this->json(['data' => $serializedData]);
    }
}
