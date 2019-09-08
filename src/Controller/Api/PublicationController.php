<?php

namespace App\Controller\Api;

use App\Entity\Publication;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PublicationController extends AbstractController
{
    /**
     * @Route("api/publications/{slug}", name="api_publications_show", options={"expose":true})
     *
     * @param Publication $publication
     *
     * @return Response
     */
    public function show(Publication $publication, \HTMLPurifier $purifier)
    {
        return $this->json([
            'title'   => $publication->getTitle(),
            'content' => $purifier->purify($publication->getContent()),
        ]);
    }
}
