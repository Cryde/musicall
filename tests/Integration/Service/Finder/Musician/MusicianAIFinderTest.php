<?php

namespace App\Tests\Integration\Service\Finder\Musician;

use App\Entity\Attribute\Instrument;
use App\Entity\Attribute\Style;
use App\Repository\Attribute\InstrumentRepository;
use App\Repository\Attribute\StyleRepository;
use App\Service\Finder\Musician\Builder\AnnounceMusicianFilterBuilder;
use App\Service\Finder\Musician\MusicianFilterGenerator;
use App\Tests\Factory\Attribute\InstrumentFactory;
use App\Tests\Factory\Attribute\StyleFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Test\InMemoryPlatform;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class MusicianAIFinderTest extends KernelTestCase
{
    public function test_find(): void
    {
        UserFactory::new()->asBaseUser()->create(['username' => 'base_user_1', 'email' => 'base_user1@email.com']);
        UserFactory::new()->asBaseUser()->create(['username' => 'base_user_2', 'email' => 'base_user2@email.com']);

        StyleFactory::new()->asPop()->create();
        $rock = StyleFactory::new()->asRock()->create();
        StyleFactory::new()->asMetal()->create();
        $drum = InstrumentFactory::new()->asDrum()->create();
        InstrumentFactory::new()->asGuitar()->create();

        $finder = $this->buildMusicianFilterGeneratorOk($drum, [$rock]);

        $result = $finder->find('Je recherche un batteur pour mon groupe de pop rock à Paris');
        $this->assertSame(1, $result->type);
        $this->assertSame($drum->id, $result->instrument);
        $this->assertCount(1, $result->styles);
        $this->assertSame($rock->id, $result->styles[0]);
        $this->assertSame(48.856614, $result->latitude);
        $this->assertSame(2.3522219, $result->longitude);
    }

    private function buildMusicianFilterGeneratorOk(Instrument $instrument, array $styles): MusicianFilterGenerator
    {
        // This is the "happy case"
        return new MusicianFilterGenerator(
            static::getContainer()->get(InstrumentRepository::class),
            static::getContainer()->get(StyleRepository::class),
            new Agent(
                new InMemoryPlatform(fn(Model $model, MessageBag $input, array $options): \Symfony\AI\Platform\Result\ResultInterface => $this->callableResult($instrument, $styles)),
                'gpt-4o-mini',
            ),
            static::getContainer()->get(AnnounceMusicianFilterBuilder::class),
        );
    }

    /**
     * @param Style[]      $styles
     */
    private function callableResult(Instrument $instrument, array $styles): ResultInterface
    {
        return new ObjectResult([
            'type' => 1,
            'instrument' => $instrument->id,
            'styles' => array_map(fn(Style $style): \Ramsey\Uuid\UuidInterface|string|null => $style->id, $styles),
            'coordinates' => [
                'latitude' => 48.856614,
                'longitude' => 2.3522219,
            ]
        ]);
    }
}
