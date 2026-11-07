<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Entity\Publication;
use App\Entity\PublicationSubCategory;
use App\Tests\Factory\Forum\ForumCategoryFactory;
use App\Tests\Factory\Forum\ForumFactory;
use App\Tests\Factory\Publication\PublicationFactory;
use App\Tests\Factory\Publication\PublicationSubCategoryFactory;
use App\Tests\Factory\Teacher\TeacherProfileFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
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

    public function test_a_french_page_shell_is_in_french_for_a_visitor_who_chose_french(): void
    {
        // As the router will show it: the choice wins over Accept-Language. The list keeps its page in memory,
        // so `?page=2` names page 1.
        $client = static::createClient();
        $client->getCookieJar()->set(new Cookie('locale', 'fr'));
        $client->request('GET', '/publications?page=2', server: ['HTTP_ACCEPT_LANGUAGE' => 'en']);

        $this->assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        $this->assertStringStartsWith("<!DOCTYPE html>\n<html lang=\"fr\" data-enabled-locales=\"fr,en\">\n", $html);
        $this->assertStringContainsString("<link rel=\"canonical\" href=\"http://localhost/publications\"/>\n", $html);
        $this->assertStringContainsString("<link rel=\"alternate\" hreflang=\"en\" href=\"http://localhost/en/publications\"/>\n", $html);
    }

    #[DataProvider('foreignBrowserLanguages')]
    public function test_a_browser_asking_for_another_language_than_french_is_sent_to_the_english_page(string $acceptLanguage): void
    {
        $client = static::createClient();
        $client->request('GET', '/cours?sort=recent', server: ['HTTP_ACCEPT_LANGUAGE' => $acceptLanguage]);

        $this->assertResponseRedirects('/en/lessons?sort=recent', Response::HTTP_FOUND);
        $this->assertSame(['Cookie', 'Accept-Language'], $client->getResponse()->getVary());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function foreignBrowserLanguages(): iterable
    {
        yield 'English' => ['en-GB,en;q=0.9'];
        yield 'German' => ['de-DE,de;q=0.9'];
        yield 'Spanish first, French second' => ['es,fr;q=0.8'];
        yield 'French with a lower quality' => ['fr;q=0.5,it'];
        yield 'English first at the same quality' => ['en,fr'];
    }

    #[DataProvider('pagesThatStayFrench')]
    public function test_a_french_page_shell_stays_french(string $acceptLanguage, ?string $cookie): void
    {
        $client = static::createClient();
        if ($cookie !== null) {
            $client->getCookieJar()->set(new Cookie($cookie, 'x'));
        }
        $client->request('GET', '/cours', server: ['HTTP_ACCEPT_LANGUAGE' => $acceptLanguage]);

        $this->assertResponseIsSuccessful();
        $this->assertStringStartsWith(
            "<!DOCTYPE html>\n<html lang=\"fr\" data-enabled-locales=\"fr,en\">\n",
            (string) $client->getResponse()->getContent(),
        );
        $this->assertSame(['Cookie', 'Accept-Language'], $client->getResponse()->getVary());
    }

    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function pagesThatStayFrench(): iterable
    {
        yield 'a French browser' => ['fr-BE,en;q=0.9', null];
        yield 'French first at the same quality' => ['fr-CA,en', null];
        // Empty rather than absent: Request::create() would otherwise send `en-us,en;q=0.5`.
        yield 'no browser language, as a search engine' => ['', null];
        yield 'any language' => ['*', null];
        yield 'a header too long to parse' => ['en,' . str_repeat('fr;q=0.1,', 40), null];
        yield 'a visitor who chose a language' => ['en', 'locale'];
        yield 'a member, whose account names their language' => ['en', 'refresh_token'];
    }

    public function test_the_home_page_sends_a_foreign_browser_to_the_english_home_page(): void
    {
        $client = static::createClient();
        $client->request('GET', '/', server: ['HTTP_ACCEPT_LANGUAGE' => 'it']);

        $this->assertResponseRedirects('/en', Response::HTTP_FOUND);
    }

    public function test_a_french_text_sends_a_foreign_browser_to_its_english_translation(): void
    {
        $client = static::createClient();
        $french = PublicationFactory::createOne(['slug' => 'un-article', 'type' => Publication::TYPE_TEXT, 'language' => 'fr', 'status' => Publication::STATUS_ONLINE]);
        $english = PublicationFactory::createOne(['slug' => 'an-article', 'type' => Publication::TYPE_TEXT, 'language' => 'en', 'status' => Publication::STATUS_ONLINE]);
        $english->joinTranslationGroupOf($french);
        self::getContainer()->get(EntityManagerInterface::class)->flush();
        $client->request('GET', '/publications/un-article?ref=newsletter', server: ['HTTP_ACCEPT_LANGUAGE' => 'en']);

        $this->assertResponseRedirects('/en/publications/an-article?ref=newsletter', Response::HTTP_FOUND);
    }

    public function test_a_video_sends_a_foreign_browser_to_its_english_page(): void
    {
        $client = static::createClient();
        PublicationFactory::createOne(['slug' => 'une-video', 'type' => Publication::TYPE_VIDEO, 'status' => Publication::STATUS_ONLINE]);
        $client->request('GET', '/publications/une-video', server: ['HTTP_ACCEPT_LANGUAGE' => 'en']);

        $this->assertResponseRedirects('/en/publications/une-video', Response::HTTP_FOUND);
    }

    public function test_a_french_page_without_an_english_version_stays_french(): void
    {
        $client = static::createClient();
        ForumFactory::createOne(['slug' => 'amplis', 'forumCategory' => ForumCategoryFactory::new(['locale' => 'fr'])]);
        $client->request('GET', '/forums/amplis', server: ['HTTP_ACCEPT_LANGUAGE' => 'en']);

        $this->assertResponseIsSuccessful();
        $this->assertStringStartsWith(
            "<!DOCTYPE html>\n<html lang=\"fr\" data-enabled-locales=\"fr,en\">\n",
            (string) $client->getResponse()->getContent(),
        );
    }

    public function test_an_english_page_shell_is_not_redirected(): void
    {
        $client = static::createClient();
        $client->request('GET', '/en/lessons', server: ['HTTP_ACCEPT_LANGUAGE' => 'de']);

        $this->assertResponseIsSuccessful();
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

    public function test_a_teacher_page_shell_carries_its_title_description_canonical_and_schema(): void
    {
        $client = static::createClient();
        $user = UserFactory::new(['username' => 'prof_shell'])->create();
        $user->profile->displayName = 'Julien Roche';
        $user->profile->location = 'Bruxelles';
        \Zenstruck\Foundry\Persistence\save($user);
        TeacherProfileFactory::createOne(['user' => $user, 'courseTitle' => null, 'offersTrial' => false]);

        $client->request('GET', '/u/prof_shell/teacher?from=search', server: ['HTTP_ACCEPT_LANGUAGE' => 'fr']);

        $this->assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        $this->assertStringContainsString('<title>Cours de musique avec Julien Roche : musique, à Bruxelles - MusicAll</title>', $html);
        $this->assertStringContainsString('<meta name="description" content="Julien Roche donne des cours sur MusicAll : musique."/>', $html);
        $this->assertStringContainsString("<meta name='robots' content='index, follow'/>", $html);
        // The query is how the visitor arrived, not another page.
        $this->assertStringContainsString('<link rel="canonical" href="http://localhost/u/prof_shell/teacher"/>', $html);
        $this->assertStringContainsString('<script type="application/ld+json">{"@context":"https://schema.org","@type":"Service","serviceType":"Cours de musique : Musique","url":"http://localhost/u/prof_shell/teacher","provider":{"@type":"Person","name":"Julien Roche","alternateName":"@prof_shell","url":"http://localhost/u/prof_shell","homeLocation":{"@type":"Place","name":"Bruxelles"}}}</script>', $html);
    }

    public function test_an_english_teacher_page_shell_is_described_in_english(): void
    {
        $client = static::createClient();
        $user = UserFactory::new(['username' => 'prof_en'])->create();
        $user->profile->location = null;
        \Zenstruck\Foundry\Persistence\save($user);
        TeacherProfileFactory::createOne(['user' => $user, 'courseTitle' => null, 'offersTrial' => false]);

        $client->request('GET', '/en/u/prof_en/teacher', server: ['HTTP_ACCEPT_LANGUAGE' => 'fr']);

        $this->assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        $this->assertStringContainsString('<title>Music lessons with prof_en: music - MusicAll</title>', $html);
        $this->assertStringContainsString('"url":"http://localhost/en/u/prof_en/teacher"', $html);
    }

    public function test_a_private_profile_shell_is_noindex_and_says_nothing_about_the_member(): void
    {
        $client = static::createClient();
        $user = UserFactory::new(['username' => 'prive_shell'])->create();
        $user->profile->isPublic = false;
        $user->profile->bio = 'Rien à voir ici';
        \Zenstruck\Foundry\Persistence\save($user);

        $client->request('GET', '/u/prive_shell', server: ['HTTP_ACCEPT_LANGUAGE' => 'fr']);

        $this->assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        $this->assertStringContainsString('<title>Profil privé - MusicAll</title>', $html);
        $this->assertStringContainsString("<meta name='robots' content='noindex, follow'/>", $html);
        $this->assertStringNotContainsString('application/ld+json', $html);
        $this->assertStringNotContainsString('Rien à voir ici', $html);
    }

    public function test_a_bio_cannot_close_the_schema_script(): void
    {
        $client = static::createClient();
        $user = UserFactory::new(['username' => 'bio_piege'])->create();
        $user->profile->bio = '</script><script>alert(1)</script> & co';
        \Zenstruck\Foundry\Persistence\save($user);

        $client->request('GET', '/u/bio_piege', server: ['HTTP_ACCEPT_LANGUAGE' => 'fr']);

        $this->assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertStringContainsString('"description":"\u003C/script\u003E\u003Cscript\u003Ealert(1)\u003C/script\u003E \u0026 co"', $html);
        $this->assertStringContainsString('<meta name="description" content="&lt;/script&gt;&lt;script&gt;alert(1)&lt;/script&gt; &amp; co"/>', $html);
    }

    public function test_a_page_without_a_page_provider_keeps_its_plain_shell(): void
    {
        $client = static::createClient();
        $client->request('GET', '/cours', server: ['HTTP_ACCEPT_LANGUAGE' => 'fr']);

        $this->assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        $this->assertStringNotContainsString('<title>', $html);
        $this->assertStringContainsString("<meta name='robots' content='index, follow'/>", $html);
        $this->assertStringNotContainsString('application/ld+json', $html);
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

    /** The musician page folded into the profile (#1317): old links and crawlers land on its « Médias » view. */
    public function test_the_musician_page_redirects_to_the_profile_media(): void
    {
        $client = static::createClient();

        $client->request('GET', '/u/julien/musician');

        $this->assertResponseStatusCodeSame(Response::HTTP_MOVED_PERMANENTLY);
        $this->assertResponseRedirects('http://localhost/u/julien?voir=medias');
    }

    public function test_the_english_musician_page_redirects_to_the_english_profile(): void
    {
        $client = static::createClient();

        $client->request('GET', '/en/u/julien.dupont/musician');

        $this->assertResponseStatusCodeSame(Response::HTTP_MOVED_PERMANENTLY);
        $this->assertResponseRedirects('http://localhost/en/u/julien.dupont?voir=medias');
    }

    public function test_the_musician_page_redirect_keeps_the_old_link_query(): void
    {
        $client = static::createClient();

        $client->request('GET', '/u/julien/musician?from=home&utm_source=newsletter');

        $this->assertResponseStatusCodeSame(Response::HTTP_MOVED_PERMANENTLY);
        $this->assertResponseRedirects('http://localhost/u/julien?voir=medias&from=home&utm_source=newsletter');
    }

    public function test_the_musician_page_redirect_encodes_the_username(): void
    {
        $client = static::createClient();

        $client->request('GET', '/u/' . rawurlencode('jean marié') . '/musician');

        $this->assertResponseStatusCodeSame(Response::HTTP_MOVED_PERMANENTLY);
        $this->assertResponseRedirects('http://localhost/u/jean%20mari%C3%A9?voir=medias');
    }

    public function test_the_musician_page_redirect_answers_a_head_request(): void
    {
        $client = static::createClient();

        $client->request('HEAD', '/u/julien/musician');

        $this->assertResponseStatusCodeSame(Response::HTTP_MOVED_PERMANENTLY);
        $this->assertResponseRedirects('http://localhost/u/julien?voir=medias');
    }
}
