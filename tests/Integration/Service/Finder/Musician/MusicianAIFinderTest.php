<?php

namespace App\Tests\Integration\Service\Finder\Musician;

use App\ApiResource\Search\MusicianText;
use App\Exception\Musician\InvalidResultException;
use App\Exception\Musician\NoResultException;
use App\Repository\Musician\MusicianAnnounceRepository;
use App\Service\Client\OpenAI\OpenAIClient;
use App\Service\Factory\JsonTextExtractorFactory;
use App\Service\Finder\Musician\Builder\SearchModelBuilder;
use App\Service\Finder\Musician\Formatter\PromptFormatter;
use App\Service\Finder\Musician\MusicianAIFinder;
use App\Tests\Factory\Attribute\InstrumentFactory;
use App\Tests\Factory\Attribute\StyleFactory;
use App\Tests\Factory\User\MusicianAnnounceFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class MusicianAIFinderTest extends KernelTestCase
{
    use ResetDatabase, Factories;

    protected function setUp(): void
    {
        self::bootKernel();
        parent::setUp();
    }

    public function test_find(): void
    {
        $user1 = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_1', 'email' => 'base_user1@email.com']);
        $user2 = UserFactory::new()->asBaseUser()->create(['username' => 'base_user_2', 'email' => 'base_user2@email.com']);

        $pop = StyleFactory::new()->asPop()->create();
        $rock = StyleFactory::new()->asRock()->create();
        $metal = StyleFactory::new()->asMetal()->create();
        $drum = InstrumentFactory::new()->asDrum()->create();
        $guitar = InstrumentFactory::new()->asGuitar()->create();

        MusicianAnnounceFactory::new()
            ->withStyles([$metal])->withInstrument($drum)->asMusician()
            ->create(['author' => $user2]); // not taken (not good style)
        MusicianAnnounceFactory::new()
            ->withStyles([$pop])->withInstrument($guitar)->asMusician()
            ->create(['author' => $user2]); // not taken (not good instrument)
        $announce = MusicianAnnounceFactory::new()
            ->withStyles([$pop, $rock])->withInstrument($drum)->asMusician()
            ->create(['author' => $user2]); // ok
        MusicianAnnounceFactory::new()
            ->withStyles([$pop, $rock])->withInstrument($drum)->asMusician()
            ->create(['author' => $user1]); // ok (but not taken because filtered on user)
        MusicianAnnounceFactory::new()
            ->withStyles([$pop, $rock])->withInstrument($drum)->asBand()
            ->create(['author' => $user2]); // not taken (not good type)

        $finder = $this->buildMusicianAIFinderOk();
        $musicianText = (new MusicianText())->setSearch('Je recherche un batteur pour mon groupe de pop rock à Paris');

        $result = $finder->find($musicianText, $user1->_real());
        $this->assertCount(1, $result);
        $this->assertSame($announce->_real(), $result[0][0]);
    }

    public function test_find_no_content(): void
    {
        $finder = $this->buildMusicianAIFinderNoContent();
        $musicianText = (new MusicianText())->setSearch('Je recherche un batteur pour mon groupe de pop rock à Paris');

        $this->expectException(NoResultException::class);
        $this->expectExceptionMessage('One the key on the response is missing');
        $finder->find($musicianText, null);
    }

    public function test_find_no_json_in_response(): void
    {
        $finder = $this->buildMusicianAIFinderNoJsonInContent();
        $musicianText = (new MusicianText())->setSearch('Je recherche un batteur pour mon groupe de pop rock à Paris');

        $this->expectException(NoResultException::class);
        $this->expectExceptionMessage('There is no JSON in the response');
        $finder->find($musicianText, null);
    }

    public function test_find_too_much_json_in_response(): void
    {
        $finder = $this->buildMusicianAIFinderTooMuchJsonInResponse();
        $musicianText = (new MusicianText())->setSearch('Je recherche un batteur pour mon groupe de pop rock à Paris');

        $this->expectException(InvalidResultException::class);
        $this->expectExceptionMessage('Too much JSON in the response');
        $finder->find($musicianText, null);
    }

    private function buildMusicianAIFinderOk(): MusicianAIFinder
    {
        // This is the "happy case"
        return new MusicianAIFinder(
            $this->buildOpenAIClient('ok.json'),
            static::getContainer()->get(PromptFormatter::class),
            static::getContainer()->get(MusicianAnnounceRepository::class),
            static::getContainer()->get(JsonTextExtractorFactory::class),
            static::getContainer()->get(SearchModelBuilder::class),
        );
    }

    private function buildMusicianAIFinderNoContent(): MusicianAIFinder
    {
        // This is an error path where on the key in the response is missing
        return new MusicianAIFinder(
            $this->buildOpenAIClient('no_content.json'),
            static::getContainer()->get(PromptFormatter::class),
            static::getContainer()->get(MusicianAnnounceRepository::class),
            static::getContainer()->get(JsonTextExtractorFactory::class),
            static::getContainer()->get(SearchModelBuilder::class),
        );
    }

    private function buildMusicianAIFinderNoJsonInContent(): MusicianAIFinder
    {
        // This is an error path where there is no json in the response.
        return new MusicianAIFinder(
            $this->buildOpenAIClient('no_json_in_content.json'),
            static::getContainer()->get(PromptFormatter::class),
            static::getContainer()->get(MusicianAnnounceRepository::class),
            static::getContainer()->get(JsonTextExtractorFactory::class),
            static::getContainer()->get(SearchModelBuilder::class),
        );
    }

    private function buildMusicianAIFinderTooMuchJsonInResponse(): MusicianAIFinder
    {
        // This is an error path where there is too much JSON in the response.
        return new MusicianAIFinder(
            $this->buildOpenAIClient('more_than_one_json_in_response.json'),
            static::getContainer()->get(PromptFormatter::class),
            static::getContainer()->get(MusicianAnnounceRepository::class),
            static::getContainer()->get(JsonTextExtractorFactory::class),
            static::getContainer()->get(SearchModelBuilder::class),
        );
    }

    private function buildOpenAIClient(string $filename): OpenAIClient
    {
        $callback = (fn($method, $url, $options): MockResponse => new MockResponse(file_get_contents(__DIR__ . '/fixtures/' . $filename)));

        return new OpenAIClient(new MockHttpClient($callback));
    }
}