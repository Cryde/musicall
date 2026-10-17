<?php

declare(strict_types=1);

namespace App\Tests\Integration\Template;

use Symfony\Bridge\Twig\AppVariable;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;

/**
 * The `<html>` tag both page templates open with. Rendered on its own because base.html.twig needs a front end
 * build, which CI does not have for the PHP suite.
 */
class HtmlTagTest extends KernelTestCase
{
    public function test_it_names_the_request_locale_and_the_enabled_locales(): void
    {
        self::assertSame("<html lang=\"en\" data-enabled-locales=\"fr,en\">\n", $this->render('_html_tag.html.twig', 'en'));
    }

    public function test_it_leaves_the_enabled_locales_out_while_french_is_the_only_one(): void
    {
        $appVariable = $this->twig()->getGlobals()['app'];
        self::assertInstanceOf(AppVariable::class, $appVariable);
        $appVariable->setEnabledLocales(['fr']);

        // Byte for byte the tag both templates had before.
        self::assertSame("<html lang=\"fr\">\n", $this->render('_html_tag.html.twig', 'fr'));
    }

    public function test_the_bot_page_opens_with_it(): void
    {
        // A cover keeps the template off the Vite asset package, which needs a front end build.
        $html = $this->render('bot_base.html.twig', 'en', ['cover' => 'https://musicall.test/cover.jpg']);

        self::assertStringStartsWith("<!DOCTYPE html>\n<html lang=\"en\" data-enabled-locales=\"fr,en\">\n<head>\n", $html);
    }

    /**
     * @param array<string, string> $context
     */
    private function render(string $template, string $locale, array $context = []): string
    {
        $request = Request::create('https://musicall.test/');
        $request->setLocale($locale);
        self::getContainer()->get(RequestStack::class)->push($request);

        return $this->twig()->render($template, $context);
    }

    private function twig(): Environment
    {
        return self::getContainer()->get('twig');
    }
}
