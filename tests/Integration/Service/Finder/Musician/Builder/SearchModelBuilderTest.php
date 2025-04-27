<?php

namespace App\Tests\Integration\Service\Finder\Musician\Builder;

use App\Exception\Musician\InvalidFormatReturnedException;
use App\Service\Finder\Musician\Builder\SearchModelBuilder;
use App\Tests\Factory\Attribute\InstrumentFactory;
use App\Tests\Factory\Attribute\StyleFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class SearchModelBuilderTest extends KernelTestCase
{
    use ResetDatabase, Factories;

    private SearchModelBuilder $searchModelBuilder;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->searchModelBuilder = static::getContainer()->get(SearchModelBuilder::class);
        parent::setUp();
    }

    public function test_build()
    {
        $style1 = StyleFactory::new()->asRock()->create();
        $style2 = StyleFactory::new()->asPop()->create();
        $instrument1 = InstrumentFactory::new()->asDrum()->create();

        $result = $this->searchModelBuilder->build('
        {
            "type": 1,
            "instrument": "batterie",
            "styles": ["pop", "rock"],
            "latitude": 48.856614,
            "longitude": 2.3522219
        }
        ');
        $this->assertSame(1, $result->getType());
        $this->assertSame(48.856614, $result->getLatitude());
        $this->assertSame(2.3522219, $result->getLongitude());
        $this->assertSame($instrument1->_real()->getId(), $result->getInstrument());
        $this->assertCount(2, $result->getStyles());
        $this->assertSame($style2->_real()->getId(), $result->getStyles()[0]);
        $this->assertSame($style1->_real()->getId(), $result->getStyles()[1]);
    }

    public function test_build_with_no_valid_data()
    {
        $this->expectException(InvalidFormatReturnedException::class);
        $this->searchModelBuilder->build('{}');
    }
}