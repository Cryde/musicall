<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Entity\Publication;
use App\Tests\Factory\Publication\PublicationCoverFactory;
use App\Tests\Factory\Publication\PublicationFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * The page a share card crawler gets. Each publication has a cover, which keeps the template off the Vite asset
 * package and its front end build.
 */
#[ResetDatabase]
class BotPageTest extends WebTestCase
{
    private const string CRAWLER = 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $french = $this->publication('un-article', 'fr');
        $english = $this->publication('an-article', 'en');
        $english->joinTranslationGroupOf($french);
        self::getContainer()->get(EntityManagerInterface::class)->flush();
    }

    public function test_an_english_page_is_in_english_and_links_its_french_version(): void
    {
        $this->client->request('GET', '/en/publications/an-article', server: ['HTTP_USER_AGENT' => self::CRAWLER, 'HTTP_ACCEPT_LANGUAGE' => 'fr']);

        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringStartsWith("<!DOCTYPE html>\n<html lang=\"en\" data-enabled-locales=\"fr,en\">\n", $html);
        self::assertStringContainsString(
            "<meta property=\"og:locale\" content=\"en_GB\"/>\n"
            . "    <link rel=\"canonical\" href=\"http://localhost/en/publications/an-article\"/>\n"
            . "    <link rel=\"alternate\" hreflang=\"fr\" href=\"http://localhost/publications/un-article\"/>\n"
            . "    <link rel=\"alternate\" hreflang=\"en\" href=\"http://localhost/en/publications/an-article\"/>\n"
            . "    <link rel=\"alternate\" hreflang=\"x-default\" href=\"http://localhost/publications/un-article\"/>\n"
            . "\n"
            . "</head>",
            $html,
        );
        self::assertStringContainsString('<title>an-article title</title>', $html);
        self::assertStringContainsString('<meta property="og:url" content="http://localhost/en/publications/an-article"/>', $html);
    }

    public function test_a_french_page_is_in_french_whatever_the_crawler_asks_for(): void
    {
        $this->client->request('GET', '/publications/un-article', server: ['HTTP_USER_AGENT' => self::CRAWLER, 'HTTP_ACCEPT_LANGUAGE' => 'en']);

        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringStartsWith("<!DOCTYPE html>\n<html lang=\"fr\" data-enabled-locales=\"fr,en\">\n", $html);
        self::assertStringContainsString("<meta property=\"og:locale\" content=\"fr_FR\"/>\n    <link rel=\"canonical\" href=\"http://localhost/publications/un-article\"/>\n", $html);
    }

    public function test_a_text_shared_under_the_other_prefix_is_in_its_own_language(): void
    {
        $this->client->request('GET', '/en/publications/un-article', server: ['HTTP_USER_AGENT' => self::CRAWLER]);

        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringStartsWith("<!DOCTYPE html>\n<html lang=\"fr\" data-enabled-locales=\"fr,en\">\n", $html);
        self::assertStringContainsString('<link rel="canonical" href="http://localhost/publications/un-article"/>', $html);
    }

    private function publication(string $slug, string $language): Publication
    {
        $publication = PublicationFactory::createOne([
            'slug' => $slug,
            'title' => $slug . ' title',
            'type' => Publication::TYPE_TEXT,
            'language' => $language,
            'status' => Publication::STATUS_ONLINE,
        ]);
        $publication->cover = PublicationCoverFactory::createOne(['imageName' => $slug, 'imageSize' => 10, 'publication' => $publication]);

        return $publication;
    }
}
