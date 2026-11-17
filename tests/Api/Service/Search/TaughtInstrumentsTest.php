<?php

declare(strict_types=1);

namespace App\Tests\Api\Service\Search;

use App\Enum\Service\ServiceStatus;
use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use App\Tests\Factory\Attribute\InstrumentFactory;
use App\Tests\Factory\Service\ServiceFactory;
use App\Tests\Factory\Service\ServiceInstrumentFactory;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/** The teacher search's instrument filter offers only what a listed teacher teaches (#1314). */
#[ResetDatabase]
class TaughtInstrumentsTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    public function test_only_the_instruments_a_listed_teacher_teaches_are_offered(): void
    {
        $bass = InstrumentFactory::new()->asBass()->create();
        $guitar = InstrumentFactory::new()->asGuitar()->create();
        $drum = InstrumentFactory::new()->asDrum()->create();
        $piano = InstrumentFactory::new()->asPiano()->create();
        InstrumentFactory::new()->asVocals()->create();
        $teacher = ServiceFactory::new()->teaching()->create();
        ServiceInstrumentFactory::new()->create(['service' => $teacher, 'instrument' => $guitar]);
        ServiceInstrumentFactory::new()->create(['service' => $teacher, 'instrument' => $bass]);
        // Taught by a second teacher too: offered once.
        ServiceInstrumentFactory::new()->create(['service' => ServiceFactory::new()->teaching()->create(), 'instrument' => $bass]);
        ServiceInstrumentFactory::new()->create([
            'service' => ServiceFactory::new()->teaching()->create(['status' => ServiceStatus::Draft]),
            'instrument' => $drum,
        ]);
        $private = UserFactory::new()->create();
        $private->profile->isPublic = false;
        ServiceInstrumentFactory::new()->create(['service' => ServiceFactory::new()->teaching()->create(['user' => $private]), 'instrument' => $piano]);

        $this->client->request('GET', '/api/services/teaching/instruments');

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            '@context' => '/api/contexts/TaughtInstrument',
            '@id' => '/api/services/teaching/instruments',
            '@type' => 'Collection',
            'totalItems' => 2,
            'member' => [
                ['@id' => '/api/services/teaching/instruments/' . $bass->id, '@type' => 'TaughtInstrument', 'id' => $bass->id, 'name' => 'Basse', 'slug' => 'basse'],
                ['@id' => '/api/services/teaching/instruments/' . $guitar->id, '@type' => 'TaughtInstrument', 'id' => $guitar->id, 'name' => 'Guitare', 'slug' => 'guitare'],
            ],
        ]);
    }
}
