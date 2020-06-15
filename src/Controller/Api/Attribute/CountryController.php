<?php

namespace App\Controller\Api\Attribute;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Routing\Annotation\Route;

class CountryController extends AbstractController
{
    /**
     * @Route(
     *     "/api/attributes/countries",
     *     name="api_attributes_countries",
     *     methods={"GET"},
     *     options={"expose": true}
     * )
     *
     * @return JsonResponse
     */
    public function list()
    {
        return $this->json(array_map(function ($countryCode3, $countryName) {
            return ['key' => $countryCode3, 'label' => $countryName];
        }, array_keys(Countries::getAlpha3Names()), Countries::getAlpha3Names()));
    }
}
