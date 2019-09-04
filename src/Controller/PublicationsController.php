<?php

namespace App\Controller;

use App\Entity\Publication;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PublicationsController extends AbstractController
{
    /**
     * @Route("/publications/", name="publications_index")
     *
     * @return Response
     */
    public function index()
    {
        return $this->render('publications/index.html.twig');
    }

    /**
     * @Route("/publications/{slug}", name="publications_show", options={"expose":true})
     *
     * @param Publication $publication
     *
     * @return Response
     */
    public function show(Publication $publication)
    {
        return $this->render('publications/show.html.twig', ['publication' => $publication]);
    }
}
