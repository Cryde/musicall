<?php

namespace App\Tests\Integration\Service\Finder\Musician\Builder;

use App\Service\Finder\Musician\Builder\SearchModelBuilder;
use App\Tests\Factory\Attribute\InstrumentFactory;
use App\Tests\Factory\Attribute\StyleFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class SearchModelBuilderTest extends KernelTestCase
{
    use ResetDatabase, Factories;

    public function test_build(): void
    {
        $style1 = StyleFactory::new()->asRock()->create();
        $style2 = StyleFactory::new()->asPop()->create();
        $instrument1 = InstrumentFactory::new()->asDrum()->create();

        $result = new SearchModelBuilder()->build(
            1,
            $instrument1,
            [$style1, $style2],
            48.856614,
            2.3522219,
        );
        $this->assertSame(1, $result->type);
        $this->assertSame(48.856614, $result->longitude);
        $this->assertSame(2.3522219, $result->latitude);
        $this->assertSame($instrument1->_real()->getId(), $result->instrument->getId());
        $this->assertCount(2, $result->styles);
        $this->assertSame($style1->_real()->getId(), $result->styles[0]->getId());
        $this->assertSame($style2->_real()->getId(), $result->styles[1]->getId());
    }
}
