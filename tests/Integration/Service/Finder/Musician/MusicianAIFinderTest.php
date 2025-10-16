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
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Test\InMemoryPlatform;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class MusicianAIFinderTest extends KernelTestCase
{
    use ResetDatabase, Factories;

    public function test_find(): void
    {
        $user1 = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_1', 'email' => 'base_user1@email.com']);
        $user2 = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_2', 'email' => 'base_user2@email.com']);

        $pop = StyleFactory::new()->asPop()->create();
        $rock = StyleFactory::new()->asRock()->create();
        $metal = StyleFactory::new()->asMetal()->create();
        $drum = InstrumentFactory::new()->asDrum()->create();
        $guitar = InstrumentFactory::new()->asGuitar()->create();

        $finder = $this->buildMusicianFilterGeneratorOk($drum, [$rock]);

        $result = $finder->find('Je recherche un batteur pour mon groupe de pop rock à Paris');
        $this->assertSame(1, $result->type);
        $this->assertSame($drum->getId(), $result->instrument);
        $this->assertCount(1, $result->styles);
        $this->assertSame($rock->getId(), $result->styles[0]);
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
                new InMemoryPlatform(fn(Model $model, MessageBag $input, array $options) => $this->callableResult($model, $input, $options, $instrument, $styles)),
                'gpt-4o-mini',
            ),
            static::getContainer()->get(AnnounceMusicianFilterBuilder::class),
        );
    }

    /**
     * @param Style[]      $styles
     */
    private function callableResult(Model $model, MessageBag $input, array $options, Instrument $instrument, array $styles): ResultInterface
    {
        return new ToolCallResult(new ToolCall('id', 'extract_data', [
            'type' => 1,
            'instrument' => $instrument->getId(),
            'styles' => array_map(fn(Style $style) => $style->getId(), $styles),
            'coordinates' => [
                'latitude' => 48.856614,
                'longitude' => 2.3522219,
            ]
        ]));
    }
}
