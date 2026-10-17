<?php

declare(strict_types=1);

namespace App\Tests\Integration\Locale;

use App\Entity\Publication;
use App\Entity\PublicationSubCategory;
use App\Locale\LocalizedPath;
use App\Locale\PageVersions;
use App\Locale\PageVersionsResolver;
use App\Repository\Forum\ForumRepository;
use App\Repository\Forum\ForumTopicRepository;
use App\Repository\PublicationRepository;
use App\Tests\Factory\Forum\ForumCategoryFactory;
use App\Tests\Factory\Forum\ForumFactory;
use App\Tests\Factory\Forum\ForumTopicFactory;
use App\Tests\Factory\Publication\PublicationFactory;
use App\Tests\Factory\Publication\PublicationSubCategoryFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * `fr` and `en` are enabled in the test environment.
 */
#[ResetDatabase]
class PageVersionsResolverTest extends KernelTestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function sharedPagesInEveryLocale(): iterable
    {
        foreach (self::sharedPages()['in_every_locale'] as $path) {
            yield $path => [$path];
        }
    }

    /**
     * The pages the router localizes (assets/js/router/routes.test.js reads the same list).
     */
    #[DataProvider('sharedPagesInEveryLocale')]
    public function test_a_page_the_router_localizes_has_a_french_and_an_english_version(string $path): void
    {
        $versions = $this->resolve($path);

        self::assertNotNull($versions);
        self::assertSame(['fr', 'en'], array_keys($versions->paths));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function sharedPagesWithoutVersions(): iterable
    {
        foreach (self::sharedPages()['without_versions'] as $path) {
            yield $path => [$path];
        }
    }

    #[DataProvider('sharedPagesWithoutVersions')]
    public function test_a_page_the_router_leaves_without_prefix_has_no_versions(string $path): void
    {
        self::assertNull($this->resolve($path));
    }

    /**
     * @return iterable<string, array{string, string, array<string, string>}>
     */
    public static function pagesInEveryLocale(): iterable
    {
        yield 'the home page' => ['/', 'fr', ['fr' => '/', 'en' => '/en']];
        yield 'the English home page' => ['/en', 'en', ['fr' => '/', 'en' => '/en']];
        yield 'a listing' => ['/en/publications', 'en', ['fr' => '/publications', 'en' => '/en/publications']];
        yield 'a tag' => ['/publications/tag/metal', 'fr', ['fr' => '/publications/tag/metal', 'en' => '/en/publications/tag/metal']];
        yield 'a landing page' => ['/cours/category/guitare', 'fr', ['fr' => '/cours/category/guitare', 'en' => '/en/lessons/category/guitare']];
        yield 'a landing page with its French segment under /en' => ['/en/cours', 'en', ['fr' => '/cours', 'en' => '/en/lessons']];
        yield 'a search page' => ['/en/find-a-singer', 'en', ['fr' => '/rechercher-un-chanteur', 'en' => '/en/find-a-singer']];
        yield 'a legal page' => ['/mentions-legales', 'fr', ['fr' => '/mentions-legales', 'en' => '/en/legal-notice']];
        yield 'a gallery' => ['/photos/concert', 'fr', ['fr' => '/photos/concert', 'en' => '/en/photos/concert']];
        yield 'a profile' => ['/en/u/jeanne/teacher', 'en', ['fr' => '/u/jeanne/teacher', 'en' => '/en/u/jeanne/teacher']];
        yield 'the forum index' => ['/forums', 'fr', ['fr' => '/forums', 'en' => '/en/forums']];
    }

    /**
     * @param array<string, string> $paths
     */
    #[DataProvider('pagesInEveryLocale')]
    public function test_a_page_without_content_language_has_a_version_in_every_locale(string $path, string $locale, array $paths): void
    {
        $this->assertVersions($locale, $paths, $this->resolve($path));
    }

    public function test_an_english_path_to_a_page_without_prefix_has_no_versions(): void
    {
        self::assertNull($this->resolve('/en/login'));
        self::assertNull($this->resolve('/nowhere'));
    }

    public function test_a_text_is_in_its_language_and_its_online_translations(): void
    {
        $french = PublicationFactory::createOne(['slug' => 'un-article', 'type' => Publication::TYPE_TEXT, 'language' => 'fr', 'status' => Publication::STATUS_ONLINE]);
        $english = PublicationFactory::createOne(['slug' => 'an-article', 'type' => Publication::TYPE_TEXT, 'language' => 'en', 'status' => Publication::STATUS_ONLINE]);
        $english->joinTranslationGroupOf($french);
        $german = PublicationFactory::createOne(['slug' => 'ein-artikel', 'type' => Publication::TYPE_TEXT, 'language' => 'de', 'status' => Publication::STATUS_ONLINE]);
        $german->joinTranslationGroupOf($french);
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        $expected = ['fr' => '/publications/un-article', 'en' => '/en/publications/an-article'];
        $this->assertVersions('fr', $expected, $this->resolve('/publications/un-article'));
        // Asked for under the wrong prefix, it still names its own URL as the canonical one.
        $this->assertVersions('fr', $expected, $this->resolve('/en/publications/un-article'));
        $this->assertVersions('en', $expected, $this->resolve('/publications/an-article'));
    }

    public function test_a_translation_that_is_not_online_is_left_out(): void
    {
        $french = PublicationFactory::createOne(['slug' => 'un-article', 'type' => Publication::TYPE_TEXT, 'language' => 'fr', 'status' => Publication::STATUS_ONLINE]);
        $draft = PublicationFactory::createOne(['slug' => 'an-article', 'type' => Publication::TYPE_TEXT, 'language' => 'en', 'status' => Publication::STATUS_DRAFT]);
        $draft->joinTranslationGroupOf($french);
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        $this->assertVersions('fr', ['fr' => '/publications/un-article'], $this->resolve('/publications/un-article'));
    }

    public function test_a_course_lives_under_the_lessons_path(): void
    {
        PublicationFactory::createOne([
            'slug' => 'learn-the-drums',
            'type' => Publication::TYPE_TEXT,
            'language' => 'en',
            'status' => Publication::STATUS_ONLINE,
            'subCategory' => PublicationSubCategoryFactory::new(['type' => PublicationSubCategory::TYPE_COURSE]),
        ]);

        $this->assertVersions('en', ['en' => '/en/lessons/learn-the-drums'], $this->resolve('/cours/learn-the-drums'));
    }

    public function test_a_video_is_in_every_locale(): void
    {
        PublicationFactory::createOne(['slug' => 'une-video', 'type' => Publication::TYPE_VIDEO, 'status' => Publication::STATUS_ONLINE]);

        $this->assertVersions('en', ['fr' => '/publications/une-video', 'en' => '/en/publications/une-video'], $this->resolve('/en/publications/une-video'));
    }

    public function test_a_publication_not_online_has_no_versions(): void
    {
        PublicationFactory::createOne(['slug' => 'un-brouillon', 'type' => Publication::TYPE_TEXT, 'language' => 'fr', 'status' => Publication::STATUS_DRAFT]);

        self::assertNull($this->resolve('/publications/un-brouillon'));
        self::assertNull($this->resolve('/publications/does-not-exist'));
    }

    public function test_a_forum_and_its_topics_are_in_their_category_locale(): void
    {
        $forum = ForumFactory::createOne(['slug' => 'gear', 'forumCategory' => ForumCategoryFactory::new(['locale' => 'en'])]);
        ForumTopicFactory::createOne(['slug' => 'which-amp', 'forum' => $forum]);

        $this->assertVersions('en', ['en' => '/en/forums/gear'], $this->resolve('/forums/gear'));
        $this->assertVersions('en', ['en' => '/en/forums/topic/which-amp/2'], $this->resolve('/forums/topic/which-amp/2'));
        self::assertNull($this->resolve('/forums/does-not-exist'));
        self::assertNull($this->resolve('/forums/topic/does-not-exist'));
    }

    public function test_page_n_of_a_forum_keeps_its_page_number(): void
    {
        ForumFactory::createOne(['slug' => 'amplis', 'forumCategory' => ForumCategoryFactory::new(['locale' => 'fr'])]);

        $this->assertVersions('fr', ['fr' => '/forums/amplis?page=3'], $this->resolve('/forums/amplis?page=3'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function forumFirstPages(): iterable
    {
        yield 'page 1' => ['/forums/amplis?page=1'];
        yield 'page 0' => ['/forums/amplis?page=0'];
        yield 'not a number' => ['/forums/amplis?page=two'];
        yield 'a list' => ['/forums/amplis?page[]=2'];
    }

    #[DataProvider('forumFirstPages')]
    public function test_a_forum_page_number_that_is_not_beyond_the_first_is_left_out(string $uri): void
    {
        ForumFactory::createOne(['slug' => 'amplis', 'forumCategory' => ForumCategoryFactory::new(['locale' => 'fr'])]);

        $this->assertVersions('fr', ['fr' => '/forums/amplis'], $this->resolve($uri));
    }

    /**
     * @return iterable<string, array{string, string, array<string, string>}>
     */
    public static function listingsWithThePageInMemory(): iterable
    {
        yield 'publications' => ['/publications?page=2', 'fr', ['fr' => '/publications', 'en' => '/en/publications']];
        yield 'lessons' => ['/en/lessons?page=3', 'en', ['fr' => '/cours', 'en' => '/en/lessons']];
        yield 'a page that is no listing' => ['/contact?page=9', 'fr', ['fr' => '/contact', 'en' => '/en/contact']];
    }

    /**
     * @param array<string, string> $paths
     */
    #[DataProvider('listingsWithThePageInMemory')]
    public function test_a_page_number_in_the_url_of_any_other_page_is_left_out(string $uri, string $locale, array $paths): void
    {
        // These lists keep their page in memory: a `?page=` there shows page 1, a duplicate.
        $this->assertVersions($locale, $paths, $this->resolve($uri));
    }

    public function test_nothing_while_french_is_the_only_enabled_locale(): void
    {
        $container = self::getContainer();
        $frenchOnly = new PageVersionsResolver(
            ['fr'],
            'fr',
            new LocalizedPath(['fr'], 'fr'),
            $container->get(PublicationRepository::class),
            $container->get(ForumRepository::class),
            $container->get(ForumTopicRepository::class),
        );

        self::assertNull($frenchOnly->resolve(Request::create('/publications')));
    }

    /**
     * @param array<string, string> $paths
     */
    private function assertVersions(string $locale, array $paths, ?PageVersions $versions): void
    {
        self::assertNotNull($versions);
        self::assertSame(['locale' => $locale, 'paths' => $paths], ['locale' => $versions->locale, 'paths' => $versions->paths]);
    }

    /**
     * @return array{in_every_locale: list<string>, without_versions: list<string>}
     */
    private static function sharedPages(): array
    {
        $cases = json_decode((string) file_get_contents(__DIR__ . '/../../Fixtures/Locale/localized_paths.json'), true, flags: JSON_THROW_ON_ERROR);

        return $cases['pages'];
    }

    private function resolve(string $uri): ?PageVersions
    {
        return $this->resolver()->resolve(Request::create($uri));
    }

    private function resolver(): PageVersionsResolver
    {
        return self::getContainer()->get(PageVersionsResolver::class);
    }
}
