<?php declare(strict_types=1);

namespace App\Controller;

use App\Repository\PublicationRepository;
use App\Service\Bot\BotDetector;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class HomeController extends AbstractController
{
    #[Route('/publication/{slug}', name: 'redirect_old_publications')]
    public function oldPublicationRedirect(string $slug): RedirectResponse
    {
        $url = $this->generateUrl('app_homepage', [], UrlGeneratorInterface::ABSOLUTE_URL) . 'publications/' . $slug;

        return $this->redirect($url, Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route('/cours/{id<\d+>}/{slug}', name: 'redirect_old_cours')]
    public function oldCourseRedirect(int $id, string $slug, PublicationRepository $publicationRepository): RedirectResponse
    {
        $course = $publicationRepository->findOldCourseByOldId($id);
        $url = $this->generateUrl('app_homepage', [], UrlGeneratorInterface::ABSOLUTE_URL) . 'cours/' . $course->getSlug();

        return $this->redirect($url, Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route('/', name: 'app_homepage')]
    #[Route('/{route}', name: 'vue_pages', requirements: ['route' => '^(?!.*_wdt|_profiler|media|api|register\/confirm\/).+'])]
    public function index(Request $request, BotDetector $botDetector): Response
    {
        if ($botDetector->isBot($request->headers->get('User-Agent', ''))) {
            return $this->forward('\\App\\Controller\\BotController::index');
        }

        return $this->render('base.html.twig');
    }
}
