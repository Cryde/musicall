<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Entity\PublicationSubCategory;
use App\Tests\Factory\Forum\ForumCategoryFactory;
use App\Tests\Factory\Forum\ForumFactory;
use App\Tests\Factory\Publication\PublicationFactory;
use App\Tests\Factory\Publication\PublicationSubCategoryFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class HomeControllerTest extends WebTestCase
{
    public function test_old_publication_redirect(): void
    {
        $client = static::createClient();
        $client->request('GET', '/publication/my-super-slug');

        $this->assertResponseStatusCodeSame(Response::HTTP_MOVED_PERMANENTLY);
        $this->assertResponseRedirects('http://localhost/publications/my-super-slug');
    }

    public function test_an_english_page_shell_is_in_english_and_links_its_versions(): void
    {
        $client = static::createClient();
        $client->request('GET', '/en/publications', server: ['HTTP_ACCEPT_LANGUAGE' => 'fr']);

        $this->assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        $this->assertStringStartsWith("<!DOCTYPE html>\n<html lang=\"en\" data-enabled-locales=\"fr,en\">\n", $html);
        $this->assertStringContainsString(
            "<meta name=\"verify-v1\" content=\"dKTCUOuzn9n0Y5rkFus+08eZunNMKBKj7sqURPF4KIs=\"/>\n"
            . "    <link rel=\"canonical\" href=\"http://localhost/en/publications\"/>\n"
            . "    <link rel=\"alternate\" hreflang=\"fr\" href=\"http://localhost/publications\"/>\n"
            . "    <link rel=\"alternate\" hreflang=\"en\" href=\"http://localhost/en/publications\"/>\n"
            . "    <link rel=\"alternate\" hreflang=\"x-default\" href=\"http://localhost/publications\"/>\n"
            . "\n",
            $html,
        );
    }

    public function test_a_french_page_shell_is_in_french_whatever_the_browser_language(): void
    {
        // As the router will show it: the URL decides, not Accept-Language. The list keeps its page in memory,
        // so `?page=2` names page 1.
        $client = static::createClient();
        $client->request('GET', '/publications?page=2', server: ['HTTP_ACCEPT_LANGUAGE' => 'en']);

        $this->assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        $this->assertStringStartsWith("<!DOCTYPE html>\n<html lang=\"fr\" data-enabled-locales=\"fr,en\">\n", $html);
        $this->assertStringContainsString("<link rel=\"canonical\" href=\"http://localhost/publications\"/>\n", $html);
        $this->assertStringContainsString("<link rel=\"alternate\" hreflang=\"en\" href=\"http://localhost/en/publications\"/>\n", $html);
    }

    public function test_page_n_of_a_forum_shell_names_its_page(): void
    {
        $client = static::createClient();
        ForumFactory::createOne(['slug' => 'amplis', 'forumCategory' => ForumCategoryFactory::new(['locale' => 'fr'])]);
        $client->request('GET', '/forums/amplis?page=2', server: ['HTTP_ACCEPT_LANGUAGE' => 'fr']);

        $this->assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        $this->assertStringContainsString("<meta name=\"verify-v1\" content=\"dKTCUOuzn9n0Y5rkFus+08eZunNMKBKj7sqURPF4KIs=\"/>\n"
            . "    <link rel=\"canonical\" href=\"http://localhost/forums/amplis?page=2\"/>\n"
            . "\n", $html);
    }

    public function test_a_member_page_shell_keeps_the_resolved_locale_and_has_no_versions(): void
    {
        $client = static::createClient();
        $client->request('GET', '/user/settings', server: ['HTTP_ACCEPT_LANGUAGE' => 'en']);

        $this->assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        $this->assertStringStartsWith("<!DOCTYPE html>\n<html lang=\"en\" data-enabled-locales=\"fr,en\">\n", $html);
        $this->assertStringNotContainsString('rel="canonical"', $html);
        $this->assertStringNotContainsString('hreflang', $html);
    }

    public function test_the_links_name_the_site_origin_not_the_host_header(): void
    {
        $client = static::createClient();
        $client->request('GET', '/en/publications', server: ['HTTP_HOST' => 'evil.example']);

        $this->assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        $this->assertStringContainsString('<link rel="canonical" href="http://localhost/en/publications"/>', $html);
        $this->assertStringNotContainsString('evil.example', $html);
    }

    public function test_old_course_redirect(): void
    {
        $client = static::createClient();

        PublicationFactory::createOne([
            'content' => 'test',
            'oldPublicationId' => 42,
            'slug' => 'old-slug-we-care',
            'subCategory' => PublicationSubCategoryFactory::createOne(['type' => PublicationSubCategory::TYPE_COURSE,])
        ]);

        $client->request('GET', '/cours/42/we-dont-care');

        $this->assertResponseStatusCodeSame(Response::HTTP_MOVED_PERMANENTLY);
        $this->assertResponseRedirects('http://localhost/cours/old-slug-we-care');
    }
}
