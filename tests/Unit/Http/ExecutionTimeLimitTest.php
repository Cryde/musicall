<?php declare(strict_types=1);

namespace App\Tests\Unit\Http;

use App\Http\ExecutionTimeLimit;
use PHPUnit\Framework\TestCase;

class ExecutionTimeLimitTest extends TestCase
{
    private int $limitBeforeTest;

    protected function setUp(): void
    {
        $this->limitBeforeTest = (int) ini_get('max_execution_time');
    }

    protected function tearDown(): void
    {
        set_time_limit($this->limitBeforeTest);
    }

    public function test_extend_raises_a_bounded_limit_to_the_transfer_limit(): void
    {
        set_time_limit(30);

        (new ExecutionTimeLimit())->extendForTransfer();

        self::assertSame('600', ini_get('max_execution_time'));
    }

    public function test_extend_leaves_an_unlimited_process_unlimited(): void
    {
        set_time_limit(0);

        (new ExecutionTimeLimit())->extendForTransfer();

        self::assertSame('0', ini_get('max_execution_time'));
    }

    public function test_reset_puts_back_the_limit_from_before_the_first_extension(): void
    {
        set_time_limit(30);
        $executionTimeLimit = new ExecutionTimeLimit();
        $executionTimeLimit->extendForTransfer();
        $executionTimeLimit->extendForTransfer();

        $executionTimeLimit->reset();

        self::assertSame('30', ini_get('max_execution_time'));
    }

    public function test_reset_without_an_extension_changes_nothing(): void
    {
        set_time_limit(45);

        (new ExecutionTimeLimit())->reset();

        self::assertSame('45', ini_get('max_execution_time'));
    }
}
