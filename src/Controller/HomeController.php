<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    /**
     * @Route("/", name="app_homepage")
     * @Route("/{route}", name="vue_pages", requirements={"route"="^(?!.*_wdt|_profiler|api|register\/confirm\/).+"})
     *
     * @return Response
     */
    public function indexAction()
    {
        return $this->render('base.html.twig');
    }
}
