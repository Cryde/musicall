<?php

namespace App\Tests\Integration\Repository\Musician;

use App\Entity\Musician\MusicianAnnounce;
use App\Model\Search\MusicianSearch;
use App\Repository\Musician\MusicianAnnounceRepository;
use App\Tests\Factory\Attribute\InstrumentFactory;
use App\Tests\Factory\Attribute\StyleFactory;
use App\Tests\Factory\User\MusicianAnnounceFactory;
use App\Tests\Factory\User\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class MusicianAnnounceRepositoryTest extends KernelTestCase
{
    use ResetDatabase, Factories;

    private MusicianAnnounceRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->repository = static::getContainer()->get(MusicianAnnounceRepository::class);
    }

    public function test_find_by_criteria_with_type_and_instrument(): void
    {
        $guitar = InstrumentFactory::new()->asGuitar()->create();
        $drum = InstrumentFactory::new()->asDrum()->create();
        $rock = StyleFactory::new()->asRock()->create();

        $announce1 = MusicianAnnounceFactory::new()
            ->withInstrument($guitar)
            ->withStyles([$rock])
            ->asMusician()
            ->create();

        MusicianAnnounceFactory::new()
            ->withInstrument($drum)
            ->withStyles([$rock])
            ->asMusician()
            ->create();

        $searchModel = new MusicianSearch();
        $searchModel->type = MusicianAnnounce::TYPE_MUSICIAN;
        $searchModel->instrument = $guitar->_real();

        $results = $this->repository->findByCriteria($searchModel, null);

        $this->assertCount(1, $results);
        $this->assertSame($announce1->_real(), $results[0]);
    }

    public function test_find_by_criteria_filters_by_type(): void
    {
        $guitar = InstrumentFactory::new()->asGuitar()->create();
        $rock = StyleFactory::new()->asRock()->create();

        $musicianAnnounce = MusicianAnnounceFactory::new()
            ->withInstrument($guitar)
            ->withStyles([$rock])
            ->asMusician()
            ->create();

        MusicianAnnounceFactory::new()
            ->withInstrument($guitar)
            ->withStyles([$rock])
            ->asBand()
            ->create();

        $searchModel = new MusicianSearch();
        $searchModel->type = MusicianAnnounce::TYPE_MUSICIAN;
        $searchModel->instrument = $guitar->_real();

        $results = $this->repository->findByCriteria($searchModel, null);

        $this->assertCount(1, $results);
        $this->assertSame($musicianAnnounce->_real(), $results[0]);
    }

    public function test_find_by_criteria_filters_by_styles(): void
    {
        $guitar = InstrumentFactory::new()->asGuitar()->create();
        $rock = StyleFactory::new()->asRock()->create();
        $metal = StyleFactory::new()->asMetal()->create();
        $pop = StyleFactory::new()->asPop()->create();

        $announce1 = MusicianAnnounceFactory::new()
            ->withInstrument($guitar)
            ->withStyles([$rock, $pop])
            ->asMusician()
            ->create();

        MusicianAnnounceFactory::new()
            ->withInstrument($guitar)
            ->withStyles([$metal])
            ->asMusician()
            ->create();

        $searchModel = new MusicianSearch();
        $searchModel->type = MusicianAnnounce::TYPE_MUSICIAN;
        $searchModel->instrument = $guitar->_real();
        $searchModel->styles = [$rock->_real()];

        $results = $this->repository->findByCriteria($searchModel, null);

        $this->assertCount(1, $results);
        $this->assertSame($announce1->_real(), $results[0]);
    }

    public function test_find_by_criteria_excludes_current_user_announces(): void
    {
        $user1 = UserFactory::new()->asBaseUser()->create();
        $user2 = UserFactory::new()->asBaseUser()->create(['username' => 'user2', 'email' => 'user2@email.com']);;
        $guitar = InstrumentFactory::new()->asGuitar()->create();
        $rock = StyleFactory::new()->asRock()->create();

        MusicianAnnounceFactory::new()
            ->withInstrument($guitar)
            ->withStyles([$rock])
            ->asMusician()
            ->create(['author' => $user1]);

        $announce2 = MusicianAnnounceFactory::new()
            ->withInstrument($guitar)
            ->withStyles([$rock])
            ->asMusician()
            ->create(['author' => $user2]);

        $searchModel = new MusicianSearch();
        $searchModel->type = MusicianAnnounce::TYPE_MUSICIAN;
        $searchModel->instrument = $guitar->_real();

        $results = $this->repository->findByCriteria($searchModel, $user1->_real());

        $this->assertCount(1, $results);
        $this->assertSame($announce2->_real(), $results[0]);
    }

    public function test_find_by_criteria_with_latitude_longitude_calculates_distance(): void
    {
        $guitar = InstrumentFactory::new()->asGuitar()->create();
        $rock = StyleFactory::new()->asRock()->create();

        $announceParٍis = MusicianAnnounceFactory::new()
            ->withInstrument($guitar)
            ->withStyles([$rock])
            ->asMusician()
            ->create([
                'locationName' => 'Paris',
                'latitude' => '48.8566',
                'longitude' => '2.3522',
            ]);

        $announceMarseille = MusicianAnnounceFactory::new()
            ->withInstrument($guitar)
            ->withStyles([$rock])
            ->asMusician()
            ->create([
                'locationName' => 'Marseille',
                'latitude' => '43.2965',
                'longitude' => '5.3698',
            ]);

        $searchModel = new MusicianSearch();
        $searchModel->type = MusicianAnnounce::TYPE_MUSICIAN;
        $searchModel->instrument = $guitar->_real();
        $searchModel->latitude = 48.8566; // Coordonnées de Paris
        $searchModel->longitude = 2.3522;

        $results = $this->repository->findByCriteria($searchModel, null);

        $this->assertCount(2, $results);

        $this->assertSame($announceParٍis->getId(), $results[0][0]->getId());
        $this->assertSame($announceMarseille->getId(), $results[1][0]->getId());

        $this->assertArrayHasKey('distance', $results[0]);
        $this->assertArrayHasKey('distance', $results[1]);
        $this->assertEqualsWithDelta(0.0, $results[0]['distance'], 0.1);
        $this->assertSame(660.476928300675, $results[1]['distance'] / 1000);
    }

    public function test_find_by_criteria_without_latitude_longitude_orders_by_creation_date(): void
    {
        $guitar = InstrumentFactory::new()->asGuitar()->create();
        $rock = StyleFactory::new()->asRock()->create();

        $announce1 = MusicianAnnounceFactory::new()
            ->withInstrument($guitar)
            ->withStyles([$rock])
            ->asMusician()
            ->create(['creationDatetime' => new \DateTime('2020-01-01')]);

        $announce2 = MusicianAnnounceFactory::new()
            ->withInstrument($guitar)
            ->withStyles([$rock])
            ->asMusician()
            ->create(['creationDatetime' => new \DateTime('2022-01-01')]);

        $searchModel = new MusicianSearch();
        $searchModel->type = MusicianAnnounce::TYPE_MUSICIAN;
        $searchModel->instrument = $guitar->_real();

        $results = $this->repository->findByCriteria($searchModel, null);

        $this->assertCount(2, $results);

        $this->assertSame($announce2->_real(), $results[0]);
        $this->assertSame($announce1->_real(), $results[1]);
    }

    public function test_find_by_criteria_respects_limit(): void
    {
        $guitar = InstrumentFactory::new()->asGuitar()->create();
        $rock = StyleFactory::new()->asRock()->create();

        for ($i = 0; $i < 15; $i++) {
            MusicianAnnounceFactory::new()
                ->withInstrument($guitar)
                ->withStyles([$rock])
                ->asMusician()
                ->create();
        }

        $searchModel = new MusicianSearch();
        $searchModel->type = MusicianAnnounce::TYPE_MUSICIAN;
        $searchModel->instrument = $guitar->_real();

        $results = $this->repository->findByCriteria($searchModel, null, 5);

        $this->assertCount(5, $results);
    }

    public function test_find_by_criteria_with_multiple_styles(): void
    {
        $guitar = InstrumentFactory::new()->asGuitar()->create();
        $rock = StyleFactory::new()->asRock()->create();
        $metal = StyleFactory::new()->asMetal()->create();
        $pop = StyleFactory::new()->asPop()->create();
        $jazz = StyleFactory::new()->asJazz()->create();

        MusicianAnnounceFactory::new()
            ->withInstrument($guitar)
            ->withStyles([$jazz, $pop])
            ->asMusician()
            ->create();

        $announce1 = MusicianAnnounceFactory::new()
            ->withInstrument($guitar)
            ->withStyles([$metal, $pop])
            ->asMusician()
            ->create();

        $announce2 = MusicianAnnounceFactory::new()
            ->withInstrument($guitar)
            ->withStyles([$rock])
            ->asMusician()
            ->create();

        $searchModel = new MusicianSearch();
        $searchModel->type = MusicianAnnounce::TYPE_MUSICIAN;
        $searchModel->instrument = $guitar->_real();
        $searchModel->styles = [$rock->_real(), $metal->_real()];

        $results = $this->repository->findByCriteria($searchModel, null);

        $this->assertCount(2, $results);
        $this->assertSame($announce1->_real(), $results[0]);
        $this->assertSame($announce2->_real(), $results[1]);
    }
}
