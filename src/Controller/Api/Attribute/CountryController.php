<?php

namespace App\Controller\Api\Attribute;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Routing\Annotation\Route;

class CountryController extends AbstractController
{
    #[Route(path: '/api/attributes/countries', name: 'api_attributes_countries', options: ['expose' => true], methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json(array_map(fn(
            $countryCode3,
            $countryName
        ) => ['key' => $countryCode3, 'label' => $countryName], array_keys(Countries::getAlpha3Names()), Countries::getAlpha3Names()));
    }
}
