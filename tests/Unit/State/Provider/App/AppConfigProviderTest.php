<?php

declare(strict_types=1);

namespace App\Tests\Unit\State\Provider\App;

use ApiPlatform\Metadata\Get;
use App\State\Provider\App\AppConfigProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** The builds come from the server's `.env.local`, typed by hand: a typo must never lock anyone out. */
class AppConfigProviderTest extends TestCase
{
    public function test_reads_the_configured_builds(): void
    {
        $builds = (new AppConfigProvider('12', '15'))->provide(new Get())->android;

        $this->assertSame(12, $builds->minSupportedBuild);
        $this->assertSame(15, $builds->latestBuild);
    }

    /** @return iterable<string, array{string}> */
    public static function unreadableValues(): iterable
    {
        yield 'empty' => [''];
        yield 'zero' => ['0'];
        yield 'negative' => ['-3'];
        yield 'not a number' => ['douze'];
        yield 'a version name instead of the code' => ['1.2.0'];
        yield 'absurdly large' => ['99999999999999999999'];
    }

    #[DataProvider('unreadableValues')]
    public function test_an_unreadable_minimum_blocks_nobody(string $value): void
    {
        $this->assertSame(1, (new AppConfigProvider($value, '5'))->provide(new Get())->android->minSupportedBuild);
    }

    public function test_surrounding_spaces_are_ignored(): void
    {
        $this->assertSame(7, (new AppConfigProvider(' 7 ', '7'))->provide(new Get())->android->minSupportedBuild);
    }

    /** Otherwise a build at the minimum would be told it is up to date and blocked at the same time. */
    public function test_latest_is_never_below_the_minimum(): void
    {
        $this->assertSame(9, (new AppConfigProvider('9', '4'))->provide(new Get())->android->latestBuild);
    }
}
