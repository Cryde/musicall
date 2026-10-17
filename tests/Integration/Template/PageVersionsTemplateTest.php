<?php

declare(strict_types=1);

namespace App\Tests\Integration\Template;

use App\Locale\PageVersions;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;

/**
 * The canonical and hreflang links both page templates include, named from DEFAULT_URI (http://localhost in
 * .env.test) and not from the request, whose host here is musicall.test.
 */
class PageVersionsTemplateTest extends KernelTestCase
{
    public function test_it_links_every_version_and_names_french_the_default(): void
    {
        $versions = new PageVersions('en', ['fr' => '/cours', 'en' => '/en/lessons'], 'fr');

        self::assertSame(
            "\n"
            . "    <link rel=\"canonical\" href=\"http://localhost/en/lessons\"/>\n"
            . "    <link rel=\"alternate\" hreflang=\"fr\" href=\"http://localhost/cours\"/>\n"
            . "    <link rel=\"alternate\" hreflang=\"en\" href=\"http://localhost/en/lessons\"/>\n"
            . "    <link rel=\"alternate\" hreflang=\"x-default\" href=\"http://localhost/cours\"/>\n",
            $this->render($versions),
        );
    }

    public function test_a_page_in_one_locale_only_gets_its_canonical_url(): void
    {
        $versions = new PageVersions('en', ['en' => '/en/forums/gear'], 'fr');

        self::assertSame("\n    <link rel=\"canonical\" href=\"http://localhost/en/forums/gear\"/>\n", $this->render($versions));
    }

    public function test_an_english_text_with_no_french_version_has_no_x_default(): void
    {
        $versions = new PageVersions('en', ['en' => '/en/publications/an-article', 'de' => '/de/publications/ein-artikel'], 'fr');

        self::assertStringNotContainsString('x-default', $this->render($versions));
    }

    public function test_it_renders_nothing_without_versions(): void
    {
        // The page is then byte for byte what it was, production included while French is the only locale.
        self::assertSame('', $this->render(null));
    }

    private function render(?PageVersions $versions): string
    {
        self::getContainer()->get(RequestStack::class)->push(Request::create('https://musicall.test/'));
        $twig = self::getContainer()->get('twig');
        self::assertInstanceOf(Environment::class, $twig);

        return $twig->render('_page_versions.html.twig', ['versions' => $versions]);
    }
}
