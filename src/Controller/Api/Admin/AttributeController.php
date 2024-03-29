<?php

namespace App\Controller\Api\Admin;

use App\Entity\Attribute\Instrument;
use App\Entity\Attribute\Style;
use App\Service\Slugifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AttributeController extends AbstractController
{
    public function __construct(
        private readonly SerializerInterface    $serializer,
        private readonly ValidatorInterface     $validator,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route(path: '/api/admin/attributes/style', name: 'api_admin_attribute_style_add', options: ['expose' => true], methods: ['POST'])]
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

    #[IsGranted('ROLE_ADMIN')]
    #[Route(path: '/api/admin/attributes/instrument', name: 'api_admin_attribute_instrument_add', options: ['expose' => true], methods: ['POST'])]
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
