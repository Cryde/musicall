<?php

namespace App\Controller\Api\Admin;

use App\Entity\Attribute\Instrument;
use App\Entity\Attribute\Style;
use App\Service\Slugifier;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AttributeController extends AbstractController
{
    private SerializerInterface $serializer;
    private ValidatorInterface $validator;
    private EntityManagerInterface $entityManager;

    public function __construct(
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        EntityManagerInterface $entityManager
    ) {
        $this->serializer = $serializer;
        $this->validator = $validator;
        $this->entityManager = $entityManager;
    }

    /**
     * @Route(
     *     "/api/admin/attributes/style",
     *     name="api_admin_attribute_style_add",
     *     methods={"POST"},
     *     options={"expose": true}
     * )
     *
     * @IsGranted("ROLE_ADMIN")
     */
    public function addStyle(Request $request, Slugifier $slugifier): JsonResponse
    {
        /** @var Style $style */
        $style = $this->serializer->deserialize($request->getContent(), Style::class, 'json');
        $style->setSlug($slugifier->create($style, 'name'));

        $errors = $this->validator->validate($style);

        if (count($errors) > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($style);
        $this->entityManager->flush();

        return $this->json([], Response::HTTP_CREATED);
    }

    /**
     * @Route(
     *     "/api/admin/attributes/instrument",
     *     name="api_admin_attribute_instrument_add",
     *     methods={"POST"},
     *     options={"expose": true}
     * )
     *
     * @IsGranted("ROLE_ADMIN")
     */
    public function addInstrument(Request $request, Slugifier $slugifier): JsonResponse
    {
        /** @var Instrument $instrument */
        $instrument = $this->serializer->deserialize($request->getContent(), Instrument::class, 'json');
        $instrument->setSlug($slugifier->create($instrument, 'name'));

        $errors = $this->validator->validate($instrument);

        if (count($errors) > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($instrument);
        $this->entityManager->flush();

        return $this->json([], Response::HTTP_CREATED);
    }
}
