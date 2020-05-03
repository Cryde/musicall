<?php

namespace App\Controller;

use App\Repository\PublicationRepository;
use App\Service\Bot\BotDetector;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class HomeController extends AbstractController
{
    /**
     * @Route("/publication/{slug}", name="redirect_old_publications")
     *
     * @param string $slug
     *
     * @return RedirectResponse
     */
    public function oldPublicationRedirect(string $slug)
    {
        $url = $this->generateUrl('app_homepage', [], UrlGeneratorInterface::ABSOLUTE_URL) . 'publications/' . $slug;

        return $this->redirect($url, Response::HTTP_MOVED_PERMANENTLY);
    }

    /**
     * @Route("/cours/{id<\d+>}/{slug}", name="redirect_old_cours")
     *
     * @param string                $id
     * @param string                $slug
     * @param PublicationRepository $publicationRepository
     *
     * @return RedirectResponse
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function oldCourseRedirect(string $id, string $slug, PublicationRepository $publicationRepository)
    {
        $course = $publicationRepository->findOldCourseByOldId($id);
        $url = $this->generateUrl('app_homepage', [], UrlGeneratorInterface::ABSOLUTE_URL) . 'cours/' . $course->getSlug();

        return $this->redirect($url, Response::HTTP_MOVED_PERMANENTLY);
    }

    /**
     * @Route("/", name="app_homepage")
     * @Route("/{route}", name="vue_pages", requirements={"route"="^(?!.*_wdt|_profiler|media|api|register\/confirm\/).+"})
     *
     * @param Request     $request
     * @param BotDetector $botDetector
     *
     * @return Response
     */
    public function index(Request $request, BotDetector $botDetector)
    {
        if ($botDetector->isBot($request->headers->get('User-Agent', ''))) {
            return $this->forward('\\App\\Controller\\BotController::index');
        }

        return $this->render('base.html.twig');
    }
}
