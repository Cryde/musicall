<?php declare(strict_types=1);

namespace App\Controller;

use App\Locale\BrowserLocaleRedirect;
use App\Locale\PageVersionsResolver;
use App\Locale\RequestLocaleResolver;
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
        $url = $this->generateUrl('app_homepage', [], UrlGeneratorInterface::ABSOLUTE_URL) . 'cours/' . $course->slug;

        return $this->redirect($url, Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route('/', name: 'app_homepage')]
    #[Route('/{route}', name: 'vue_pages', requirements: ['route' => '^(?!.*_wdt|_profiler|media|api|oauth|register\/confirm\/).+'])]
    public function index(
        Request $request,
        BotDetector $botDetector,
        PageVersionsResolver $pageVersionsResolver,
        BrowserLocaleRedirect $browserLocaleRedirect,
    ): Response {
        if ($botDetector->isBot($request->headers->get('User-Agent', ''))) {
            return $this->forward('\\App\\Controller\\BotController::index');
        }

        // Search engines get this page, not the bot one: BotDetector only knows the crawlers of share cards.
        $versions = $pageVersionsResolver->resolve($request);
        // The others (a member's own, the sign-in flow) keep the locale the request resolved.
        if ($versions === null) {
            return $this->render('base.html.twig', ['versions' => null]);
        }

        // A page with a version per locale is in its URL's locale, or its content's, as the router will show it.
        $request->setLocale($versions->locale);
        $target = $browserLocaleRedirect->target($request, $versions);
        // Whether it redirects depends on the browser language and the cookies.
        $request->attributes->set(RequestLocaleResolver::VARY_BY_LANGUAGE, true);
        $response = $target !== null
            ? $this->redirect($target)
            : $this->render('base.html.twig', ['versions' => $versions]);
        $response->setVary('Cookie', false);

        return $response;
    }
}
