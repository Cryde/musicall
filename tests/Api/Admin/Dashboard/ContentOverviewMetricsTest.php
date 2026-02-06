<?php

declare(strict_types=1);

namespace App\Tests\Api\Admin\Dashboard;

use App\Entity\Musician\MusicianAnnounce;
use App\Entity\Publication;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Attribute\InstrumentFactory;
use App\Tests\Factory\Attribute\StyleFactory;
use App\Tests\Factory\Forum\ForumPostFactory;
use App\Tests\Factory\Forum\ForumTopicFactory;
use App\Tests\Factory\Metric\ViewCacheFactory;
use App\Tests\Factory\Publication\PublicationFactory;
use App\Tests\Factory\Publication\PublicationSubCategoryFactory;
use App\Tests\Factory\User\MusicianAnnounceFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class ContentOverviewMetricsTest extends ApiTestCase
{
    use ResetDatabase, Factories;
    use ApiTestAssertionsTrait;

    public function test_get_content_overview_as_admin(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create()->_real();
        $today = (new \DateTimeImmutable())->format('Y-m-d');

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/admin/dashboard/content-overview?from=' . $today . '&to=' . $today);
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ContentOverviewMetrics',
            '@id' => '/api/admin/dashboard/content-overview',
            '@type' => 'ContentOverviewMetrics',
            'from' => $today,
            'to' => $today,
            'publications_by_type' => [],
            'top_content' => [],
            'publications_by_format' => [],
            'forum_topics_count' => 0,
            'forum_posts_count' => 0,
            'announces_by_type' => [],
            'top_instruments' => [],
            'top_styles' => [],
        ]);
    }

    public function test_get_content_overview_with_data(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create()->_real();
        $today = (new \DateTimeImmutable())->format('Y-m-d');

        $sub = PublicationSubCategoryFactory::new()->asChronique()->create();
        $viewCache = ViewCacheFactory::new(['count' => 42])->create();
        $publication = PublicationFactory::new([
            'author' => $admin,
            'title' => 'Test Content Overview',
            'type' => Publication::TYPE_TEXT,
            'status' => Publication::STATUS_ONLINE,
            'subCategory' => $sub,
            'publicationDatetime' => new \DateTime(),
            'viewCache' => $viewCache,
        ])->create()->_real();

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/admin/dashboard/content-overview?from=' . $today . '&to=' . $today);
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ContentOverviewMetrics',
            '@id' => '/api/admin/dashboard/content-overview',
            '@type' => 'ContentOverviewMetrics',
            'from' => $today,
            'to' => $today,
            'publications_by_type' => [
                'chroniques' => 1,
            ],
            'top_content' => [
                [
                    'id' => $publication->getId(),
                    'title' => 'Test Content Overview',
                    'views' => 42,
                    'type' => 'text',
                ],
            ],
            'publications_by_format' => [
                'text' => 1,
            ],
            'forum_topics_count' => 0,
            'forum_posts_count' => 0,
            'announces_by_type' => [],
            'top_instruments' => [],
            'top_styles' => [],
        ]);
    }

    public function test_get_content_overview_with_forum_and_announces(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create()->_real();
        $today = (new \DateTimeImmutable())->format('Y-m-d');

        // Forum data: 2 topics today, 3 posts today (assigned to a shared topic)
        $topic = ForumTopicFactory::new(['creationDatetime' => new \DateTime()])->create();
        ForumTopicFactory::new(['creationDatetime' => new \DateTime()])->create();
        // 1 topic outside range (should be excluded)
        ForumTopicFactory::new(['creationDatetime' => new \DateTime('-10 days')])->create();

        ForumPostFactory::new(['creationDatetime' => new \DateTime(), 'topic' => $topic])->create();
        ForumPostFactory::new(['creationDatetime' => new \DateTime(), 'topic' => $topic])->create();
        ForumPostFactory::new(['creationDatetime' => new \DateTime(), 'topic' => $topic])->create();

        // Musician announces: 2 musicians + 1 band, with instruments and styles
        $guitar = InstrumentFactory::new()->asGuitar()->create();
        $drum = InstrumentFactory::new()->asDrum()->create();
        $rock = StyleFactory::new()->asRock()->create();
        $jazz = StyleFactory::new()->asJazz()->create();

        MusicianAnnounceFactory::new([
            'creationDatetime' => new \DateTime(),
            'type' => MusicianAnnounce::TYPE_MUSICIAN,
        ])->withInstrument($guitar)->withStyles([$rock])->create();
        MusicianAnnounceFactory::new([
            'creationDatetime' => new \DateTime(),
            'type' => MusicianAnnounce::TYPE_MUSICIAN,
        ])->withInstrument($guitar)->withStyles([$rock])->create();
        MusicianAnnounceFactory::new([
            'creationDatetime' => new \DateTime(),
            'type' => MusicianAnnounce::TYPE_BAND,
        ])->withInstrument($drum)->withStyles([$jazz])->create();
        // 1 announce outside range (should be excluded)
        MusicianAnnounceFactory::new([
            'creationDatetime' => new \DateTime('-10 days'),
            'type' => MusicianAnnounce::TYPE_MUSICIAN,
        ])->withInstrument($guitar)->create();

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/admin/dashboard/content-overview?from=' . $today . '&to=' . $today);
        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/ContentOverviewMetrics',
            '@id' => '/api/admin/dashboard/content-overview',
            '@type' => 'ContentOverviewMetrics',
            'from' => $today,
            'to' => $today,
            'publications_by_type' => [],
            'top_content' => [],
            'publications_by_format' => [],
            'forum_topics_count' => 2,
            'forum_posts_count' => 3,
            'announces_by_type' => [
                'musician' => 2,
                'band' => 1,
            ],
            'top_instruments' => [
                ['name' => 'Guitare', 'count' => 2],
                ['name' => 'Batterie', 'count' => 1],
            ],
            'top_styles' => [
                ['name' => 'Rock', 'count' => 2],
                ['name' => 'Jazz', 'count' => 1],
            ],
        ]);
    }

    public function test_get_content_overview_not_logged(): void
    {
        $today = (new \DateTimeImmutable())->format('Y-m-d');

        $this->client->request('GET', '/api/admin/dashboard/content-overview?from=' . $today . '&to=' . $today);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertJsonEquals([
            'code' => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function test_get_content_overview_as_normal_user(): void
    {
        $user = UserFactory::new()->asBaseUser()->create()->_real();
        $today = (new \DateTimeImmutable())->format('Y-m-d');

        $this->client->loginUser($user);
        $this->client->request('GET', '/api/admin/dashboard/content-overview?from=' . $today . '&to=' . $today);
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function test_get_content_overview_missing_from(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create()->_real();
        $today = (new \DateTimeImmutable())->format('Y-m-d');

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/admin/dashboard/content-overview?to=' . $today);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_get_content_overview_missing_to(): void
    {
        $admin = UserFactory::new()->asAdminUser()->create()->_real();
        $today = (new \DateTimeImmutable())->format('Y-m-d');

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/admin/dashboard/content-overview?from=' . $today);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
