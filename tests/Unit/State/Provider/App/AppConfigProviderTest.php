<?php

declare(strict_types=1);

namespace App\Tests\Unit\State\Provider\App;

use ApiPlatform\Metadata\Get;
use App\ApiResource\App\AppConfig;
use App\State\Provider\App\AppConfigProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** The builds come from the server's `.env.local`, typed by hand: a typo must never lock anyone out. */
class AppConfigProviderTest extends TestCase
{
    public function test_reads_the_configured_builds_of_each_platform(): void
    {
        $config = self::provide(androidMin: '12', androidLatest: '15', iosMin: '10', iosLatest: '14');

        $this->assertSame(12, $config->android->minSupportedBuild);
        $this->assertSame(15, $config->android->latestBuild);
        $this->assertSame(10, $config->ios->minSupportedBuild);
        $this->assertSame(14, $config->ios->latestBuild);
    }

    /** The platforms release apart, so raising one must leave the other where it was. */
    public function test_a_platform_left_unset_is_not_raised_by_the_other(): void
    {
        $androidOnly = self::provide(androidMin: '12', androidLatest: '15');
        $this->assertSame(1, $androidOnly->ios->minSupportedBuild);
        $this->assertSame(1, $androidOnly->ios->latestBuild);

        $iosOnly = self::provide(iosMin: '12', iosLatest: '15');
        $this->assertSame(1, $iosOnly->android->minSupportedBuild);
        $this->assertSame(1, $iosOnly->android->latestBuild);
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
    public function test_an_unreadable_android_minimum_blocks_nobody(string $value): void
    {
        $this->assertSame(1, self::provide(androidMin: $value, androidLatest: '5')->android->minSupportedBuild);
    }

    #[DataProvider('unreadableValues')]
    public function test_an_unreadable_ios_minimum_blocks_nobody(string $value): void
    {
        $this->assertSame(1, self::provide(iosMin: $value, iosLatest: '5')->ios->minSupportedBuild);
    }

    public function test_surrounding_spaces_are_ignored(): void
    {
        $config = self::provide(androidMin: ' 7 ', androidLatest: '7', iosMin: ' 8 ', iosLatest: '8');

        $this->assertSame(7, $config->android->minSupportedBuild);
        $this->assertSame(8, $config->ios->minSupportedBuild);
    }

    /** Otherwise a build at the minimum would be told it is up to date and blocked at the same time. */
    public function test_latest_is_never_below_the_minimum_on_either_platform(): void
    {
        $config = self::provide(androidMin: '9', androidLatest: '4', iosMin: '6', iosLatest: '2');

        $this->assertSame(9, $config->android->latestBuild);
        $this->assertSame(6, $config->ios->latestBuild);
    }

    private static function provide(
        string $androidMin = '1',
        string $androidLatest = '1',
        string $iosMin = '1',
        string $iosLatest = '1',
    ): AppConfig {
        return (new AppConfigProvider($androidMin, $androidLatest, $iosMin, $iosLatest))->provide(new Get());
    }
}
