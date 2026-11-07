<?php declare(strict_types=1);

namespace App\Controller;

use App\Http\MobileAppStore;
use App\Locale\BrowserLocaleRedirect;
use App\Locale\LocalizedPath;
use App\Locale\PageVersionsResolver;
use App\Locale\RequestLocaleResolver;
use App\Repository\PublicationRepository;
use App\Service\Bot\BotDetector;
use App\Service\Bot\BotMetaDataGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Translation\LocaleSwitcher;

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

    /** The musician page folded into the profile (#1317): old links and crawlers land on its « Médias » view. */
    #[Route('/u/{username}/musician', name: 'redirect_musician_profile', requirements: ['username' => '[^/]+'])]
    #[Route('/en/u/{username}/musician', name: 'redirect_musician_profile_en', requirements: ['username' => '[^/]+'])]
    public function musicianProfileRedirect(string $username, Request $request, LocalizedPath $localizedPath): RedirectResponse
    {
        $profilePath = $localizedPath->inLocale('/u/' . rawurlencode($username), $localizedPath->localeOf($request->getPathInfo()));
        // The old link's own query (`from`, campaign tags) goes along, as the router's redirect keeps it.
        $query = http_build_query(['voir' => 'medias'] + $request->query->all(), '', '&', PHP_QUERY_RFC3986);
        $url = rtrim($this->generateUrl('app_homepage', [], UrlGeneratorInterface::ABSOLUTE_URL), '/') . $profilePath . '?' . $query;

        return $this->redirect($url, Response::HTTP_MOVED_PERMANENTLY);
    }

    /**
     * The QR code's target (#1339): each phone to its own store, anything else to the homepage, whose footer shows
     * both. A 302, since the answer depends on who asks.
     */
    #[Route('/app', name: 'mobile_app_redirect')]
    public function mobileAppRedirect(Request $request): RedirectResponse
    {
        $url = MobileAppStore::urlFor((string) $request->headers->get('User-Agent'))
            ?? $this->generateUrl('app_homepage', [], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->redirect($url);
    }

    #[Route('/', name: 'app_homepage')]
    #[Route('/{route}', name: 'vue_pages', requirements: ['route' => '^(?!.*_wdt|_profiler|media|api|oauth|register\/confirm\/).+'])]
    public function index(
        Request $request,
        BotDetector $botDetector,
        PageVersionsResolver $pageVersionsResolver,
        BrowserLocaleRedirect $browserLocaleRedirect,
        BotMetaDataGenerator $botMetaDataGenerator,
        LocaleSwitcher $localeSwitcher,
    ): Response {
        if ($botDetector->isBot($request->headers->get('User-Agent', ''))) {
            return $this->forward('\\App\\Controller\\BotController::index');
        }

        // Search engines get this page, not the bot one: BotDetector only knows the crawlers of share cards.
        $versions = $pageVersionsResolver->resolve($request);
        // The page's own title, description and schema.org data, in the page's locale (the member pages for now).
        $pageMeta = static fn (string $locale): array => $localeSwitcher->runWithLocale(
            $locale,
            static fn (): array => $botMetaDataGenerator->getPageMetaData($request->getPathInfo()),
        );
        // The others (a member's own, the sign-in flow) keep the locale the request resolved.
        if ($versions === null) {
            return $this->render('base.html.twig', [...$pageMeta($request->getLocale()), 'versions' => null]);
        }

        // A page with a version per locale is in its URL's locale, or its content's, as the router will show it.
        $request->setLocale($versions->locale);
        $target = $browserLocaleRedirect->target($request, $versions);
        // Whether it redirects depends on the browser language and the cookies.
        $request->attributes->set(RequestLocaleResolver::VARY_BY_LANGUAGE, true);
        $response = $target !== null
            ? $this->redirect($target)
            : $this->render('base.html.twig', [...$pageMeta($versions->locale), 'versions' => $versions]);
        $response->setVary('Cookie', false);

        return $response;
    }
}
