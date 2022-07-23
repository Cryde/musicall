<?php

namespace App\Controller\Api\Artist;

use App\Entity\Wiki\Artist;
use App\Serializer\Artist\ArtistArraySerializer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class ArtistController extends AbstractController
{
    #[Route(path: '/api/artist/{slug}', name: 'api_artist_show', options: ['expose' => true])]
    public function show(Artist $artist, ArtistArraySerializer $artistArraySerializer): JsonResponse
    {
        return $this->json($artistArraySerializer->toArray($artist));
    }
}
