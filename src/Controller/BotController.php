<?php declare(strict_types=1);

namespace App\Controller;

use App\Locale\LocalizedPath;
use App\Locale\PageVersionsResolver;
use App\Service\Bot\BotMetaDataGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Translation\LocaleSwitcher;

class BotController extends AbstractController
{
    #[Route('/bot', name: 'bot')]
    public function index(
        Request $request,
        BotMetaDataGenerator $botMetaDataGenerator,
        PageVersionsResolver $pageVersionsResolver,
        LocalizedPath $localizedPath,
        LocaleSwitcher $localeSwitcher,
    ): Response {
        $versions = $pageVersionsResolver->resolve($request);
        // A share card is in the language of the page shared, its content's when it has one, never the crawler's.
        $locale = $versions->locale ?? $localizedPath->localeOf($request->getPathInfo());
        $request->setLocale($locale);

        return $this->render('bot_base.html.twig', [
            ...$localeSwitcher->runWithLocale($locale, static fn (): array => $botMetaDataGenerator->getMetaData($request->getRequestUri())),
            'versions' => $versions,
        ]);
    }
}
