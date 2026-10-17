<?php

declare(strict_types=1);

namespace App\Tests\Api\Attribute;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Attribute\InstrumentFactory;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class InstrumentGetCollectionTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_get_instruments_sorted_by_musician_name(): void
    {
        $guitar = InstrumentFactory::new()->asGuitar()->create();
        $drum = InstrumentFactory::new()->asDrum()->create();

        $this->client->request('GET', '/api/instruments');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Instrument',
            '@id' => '/api/instruments',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/instruments/' . $drum->id,
                    '@type' => 'Instrument',
                    'id' => $drum->id,
                    'name' => 'Batterie',
                    'musician_name' => 'Batteur',
                    'slug' => 'batterie',
                ],
                [
                    '@id' => '/api/instruments/' . $guitar->id,
                    '@type' => 'Instrument',
                    'id' => $guitar->id,
                    'name' => 'Guitare',
                    'musician_name' => 'Guitariste',
                    'slug' => 'guitare',
                ],
            ],
            'totalItems' => 2,
        ]);
    }

    public function test_get_instrument(): void
    {
        $guitar = InstrumentFactory::new()->asGuitar()->create();

        $this->client->request('GET', '/api/instruments/' . $guitar->id);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Instrument',
            '@id' => '/api/instruments/' . $guitar->id,
            '@type' => 'Instrument',
            'id' => $guitar->id,
            'name' => 'Guitare',
            'musician_name' => 'Guitariste',
            'slug' => 'guitare',
        ]);
    }

    public function test_get_instruments_in_another_locale_with_the_database_name_as_fallback(): void
    {
        $guitar = InstrumentFactory::new()->asGuitar()->create();
        // One an admin has just added: no catalogue names its slug.
        $theremin = InstrumentFactory::new()->create(['name' => 'Thérémine', 'musicianName' => 'Thérémiste', 'slug' => 'theremine']);
        $this->serveIn('en_GB', ['instrument.guitare' => 'Guitar', 'instrument_musician.guitare' => 'Guitarist']);

        $this->client->request('GET', '/api/instruments');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/Instrument',
            '@id' => '/api/instruments',
            '@type' => 'Collection',
            'member' => [
                [
                    '@id' => '/api/instruments/' . $guitar->id,
                    '@type' => 'Instrument',
                    'id' => $guitar->id,
                    'name' => 'Guitar',
                    'musician_name' => 'Guitarist',
                    'slug' => 'guitare',
                ],
                [
                    '@id' => '/api/instruments/' . $theremin->id,
                    '@type' => 'Instrument',
                    'id' => $theremin->id,
                    'name' => 'Thérémine',
                    'musician_name' => 'Thérémiste',
                    'slug' => 'theremine',
                ],
            ],
            'totalItems' => 2,
        ]);
    }

    /**
     * en_GB rather than en: a catalogue is cached on disk the first time it loads and a resource added later is
     * ignored, and en is enabled, so it is warmed up. Nothing ever loads en_GB.
     *
     * @param array<string, string> $names
     */
    private function serveIn(string $locale, array $names): void
    {
        $container = static::getContainer();
        $catalogues = $container->get('translator.default');
        $catalogues->addLoader('array', new ArrayLoader());
        $catalogues->addResource('array', $names, $locale, 'reference+intl-icu');

        // After RequestLocaleListener (7), which only resolves an enabled locale. Not the LocaleSwitcher: it
        // would also change the process-wide \Locale default, which outlives this test.
        $translator = $container->get('translator');
        $container->get('event_dispatcher')->addListener(
            KernelEvents::REQUEST,
            static function (RequestEvent $event) use ($locale, $translator): void {
                $event->getRequest()->setLocale($locale);
                $translator->setLocale($locale);
            },
            0,
        );
    }
}
