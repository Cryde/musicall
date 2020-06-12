<?php

namespace App\Controller\Api\Artist;

use App\Entity\Wiki\Artist;
use App\Serializer\Artist\ArtistArraySerializer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class ArtistController extends AbstractController
{
    /**
     * @Route(
     *     "/api/artist/{slug}",
     *     name="api_artist_show",
     *     options={"expose": true}
     * )
     *
     * @param Artist                $artist
     * @param ArtistArraySerializer $artistArraySerializer
     *
     * @return JsonResponse
     */
    public function show(Artist $artist, ArtistArraySerializer $artistArraySerializer)
    {
        return $this->json($artistArraySerializer->toArray($artist));
    }
}
