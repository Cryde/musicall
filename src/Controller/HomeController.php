<?php

namespace App\Controller;

use App\Service\Bot\BotDetector;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    /**
     * @Route("/", name="app_homepage")
     * @Route("/{route}", name="vue_pages", requirements={"route"="^(?!.*_wdt|_profiler|api|register\/confirm\/).+"})
     *
     * @param Request     $request
     * @param BotDetector $botDetector
     *
     * @return Response
     */
    public function indexAction(Request $request, BotDetector $botDetector)
    {
        if ($botDetector->isBot($request->headers->get('User-Agent'))) {
            return $this->forward('\\App\\Controller\\BotController::index');
        }

        return $this->render('base.html.twig');
    }
}
