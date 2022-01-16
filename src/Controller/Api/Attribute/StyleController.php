<?php

namespace App\Controller\Api\Attribute;

use App\Repository\Attribute\StyleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

class StyleController extends AbstractController
{
    /**
     * @Route(
     *     "/api/attributes/styles",
     *     name="api_attributes_styles",
     *     methods={"GET"},
     *     options={"expose": true}
     * )
     */
    public function list(StyleRepository $styleRepository): JsonResponse
    {
        return $this->json($styleRepository->findAll(), Response::HTTP_OK, [], [
            AbstractNormalizer::IGNORED_ATTRIBUTES => ['creationDatetime']
        ]);
    }
}
