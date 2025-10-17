<?php declare(strict_types=1);

namespace App\Controller;

use App\Service\Bot\BotMetaDataGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class BotController extends AbstractController
{
    #[Route('/bot', name: 'bot')]
    public function index(Request $request, BotMetaDataGenerator $botMetaDataGenerator): Response
    {
        return $this->render('bot_base.html.twig', $botMetaDataGenerator->getMetaData($request->getRequestUri()));
    }
}
