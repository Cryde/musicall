<?php

namespace App\Controller\Api\Attribute;

use App\Repository\Attribute\InstrumentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

class InstrumentController extends AbstractController
{
    /**
     * @Route(
     *     "/api/attributes/instruments",
     *     name="api_attributes_instruments",
     *     methods={"GET"},
     *     options={"expose": true}
     * )
     *
     * @param InstrumentRepository $instrumentRepository
     *
     * @return JsonResponse
     */
    public function list(InstrumentRepository $instrumentRepository)
    {
        return $this->json($instrumentRepository->findAll(), Response::HTTP_OK, [], [
            AbstractNormalizer::IGNORED_ATTRIBUTES => ['creationDatetime']
        ]);
    }
}
