<?php

declare(strict_types=1);

namespace App\Tests\Integration\State;

use ApiPlatform\Metadata\Get;
use App\ApiResource\Search\Frequent\FrequentSearch;
use App\Entity\Search\MusicianSearchLog;
use App\Enum\Search\MusicianSearchKind;
use App\State\Provider\Search\FrequentSearchesProvider;
use App\Tests\Factory\Attribute\InstrumentFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Translation\LocaleSwitcher;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * The provider caches language neutral rows, so the entry a French visitor filled serves an English one
 * too. Driven at the provider, in one kernel, because the cache pools are emptied between two HTTP requests.
 */
#[ResetDatabase]
class FrequentSearchesProviderTest extends KernelTestCase
{
    public function test_one_cached_entry_serves_every_locale(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $drum = InstrumentFactory::new()->asDrum()->create();
        foreach (['a', 'b', 'c'] as $visitor) {
            $log = new MusicianSearchLog(MusicianSearchKind::Filters, hash('sha256', $visitor), new \DateTimeImmutable('-1 day'));
            $log->type = 2;
            $log->instrument = $drum;
            $log->locationName = 'Bruxelles';
            $log->latitude = 50.84;
            $log->longitude = 4.35;
            $entityManager->persist($log);
        }
        $entityManager->flush();
        $localeSwitcher = $container->get(LocaleSwitcher::class);
        $provider = $container->get(FrequentSearchesProvider::class);
        $names = static fn (array $searches): array => array_map(static fn (FrequentSearch $search): string => $search->instrumentName, $searches);

        $localeSwitcher->setLocale('fr');
        $french = $provider->provide(new Get());
        // Gone from the database, so the English answer can only come from the entry the French one filled.
        $entityManager->createQuery('DELETE FROM ' . MusicianSearchLog::class . ' log')->execute();
        $localeSwitcher->setLocale('en');
        $english = $provider->provide(new Get());

        $this->assertSame(['Batteur'], $names($french->searches));
        $this->assertSame(['Drummer'], $names($english->searches));
    }
}
