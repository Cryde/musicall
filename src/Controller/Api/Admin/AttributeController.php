<?php

namespace App\Controller\Api\Admin;

use App\Entity\Attribute\Instrument;
use App\Entity\Attribute\Style;
use App\Service\Slugifier;
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
    /**
     * @var SerializerInterface
     */
    private SerializerInterface $serializer;
    /**
     * @var ValidatorInterface
     */
    private ValidatorInterface $validator;

    public function __construct(SerializerInterface $serializer, ValidatorInterface $validator)
    {
        $this->serializer = $serializer;
        $this->validator = $validator;
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
     *
     * @param Request   $request
     * @param Slugifier $slugifier
     *
     * @return JsonResponse
     */
    public function addStyle(Request $request, Slugifier $slugifier)
    {
        /** @var Style $style */
        $style = $this->serializer->deserialize($request->getContent(), Style::class, 'json');
        $style->setSlug($slugifier->create($style, 'name'));

        $errors = $this->validator->validate($style);

        if (count($errors) > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $this->getDoctrine()->getManager()->persist($style);
        $this->getDoctrine()->getManager()->flush();

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
     *
     * @param Request   $request
     * @param Slugifier $slugifier
     *
     * @return JsonResponse
     */
    public function addInstrument(Request $request, Slugifier $slugifier)
    {
        /** @var Instrument $instrument */
        $instrument = $this->serializer->deserialize($request->getContent(), Instrument::class, 'json');
        $instrument->setSlug($slugifier->create($instrument, 'name'));

        $errors = $this->validator->validate($instrument);

        if (count($errors) > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $this->getDoctrine()->getManager()->persist($instrument);
        $this->getDoctrine()->getManager()->flush();

        return $this->json([], Response::HTTP_CREATED);
    }
}
