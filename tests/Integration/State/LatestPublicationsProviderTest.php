<?php declare(strict_types=1);

namespace App\Tests\Integration\State;

use ApiPlatform\Metadata\GetCollection;
use App\ApiResource\Publication\PublicationListItem;
use App\Entity\Publication;
use App\State\Provider\Publication\LatestPublicationsProvider;
use App\Tests\Factory\Publication\PublicationFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Translation\LocaleSwitcher;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * The provider caches the ids it lists, so a French list must never be served to an English page.
 * Driven at the provider, in one kernel, because the cache pools are emptied between two HTTP requests.
 */
#[ResetDatabase]
class LatestPublicationsProviderTest extends KernelTestCase
{
    public function test_each_locale_has_its_own_cached_list(): void
    {
        self::bootKernel();
        PublicationFactory::new()->create([
            'slug' => 'un-article', 'status' => Publication::STATUS_ONLINE, 'type' => Publication::TYPE_TEXT,
            'language' => 'fr', 'publicationDatetime' => new \DateTime('2022-01-03 10:00:00'),
        ]);
        PublicationFactory::new()->create([
            'slug' => 'an-article', 'status' => Publication::STATUS_ONLINE, 'type' => Publication::TYPE_TEXT,
            'language' => 'en', 'publicationDatetime' => new \DateTime('2022-01-02 10:00:00'),
        ]);
        PublicationFactory::new()->create([
            'slug' => 'v-une-video', 'status' => Publication::STATUS_ONLINE, 'type' => Publication::TYPE_VIDEO,
            'publicationDatetime' => new \DateTime('2022-01-01 10:00:00'),
        ]);
        $container = self::getContainer();
        $localeSwitcher = $container->get(LocaleSwitcher::class);
        $provider = $container->get(LatestPublicationsProvider::class);
        $context = ['filters' => ['count' => '3']];

        $localeSwitcher->setLocale('fr');
        $french = $provider->provide(new GetCollection(), [], $context);
        $localeSwitcher->setLocale('en');
        $english = $provider->provide(new GetCollection(), [], $context);

        $slugs = static fn (array $items): array => array_map(static fn (PublicationListItem $item): string => $item->slug, $items);
        $this->assertSame(['un-article', 'v-une-video'], $slugs($french));
        $this->assertSame(['an-article', 'v-une-video'], $slugs($english));
    }
}
