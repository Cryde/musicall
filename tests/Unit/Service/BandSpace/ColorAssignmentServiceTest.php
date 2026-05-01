<?php

namespace App\Tests\Unit\Service\BandSpace;

use App\Service\BandSpace\ColorAssignmentService;
use PHPUnit\Framework\TestCase;

class ColorAssignmentServiceTest extends TestCase
{
    private const array PALETTE = [
        '#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FFEAA7',
        '#DDA0DD', '#F0A500', '#74B9FF', '#A29BFE', '#FD79A8',
    ];

    private ColorAssignmentService $service;

    protected function setUp(): void
    {
        $this->service = new ColorAssignmentService();
    }

    public function test_first_color(): void
    {
        $this->assertSame('#FF6B6B', $this->service->assignColor([]));
    }

    public function test_tenth_color(): void
    {
        $usedNine = array_slice(self::PALETTE, 0, 9);
        $this->assertSame('#FD79A8', $this->service->assignColor($usedNine));
    }

    public function test_wraps_around_after_ten(): void
    {
        $this->assertSame('#FF6B6B', $this->service->assignColor(self::PALETTE));
    }

    public function test_arbitrary_count(): void
    {
        $thirteen = array_merge(self::PALETTE, ['#FF6B6B', '#4ECDC4', '#45B7D1']);
        $this->assertSame('#96CEB4', $this->service->assignColor($thirteen));
    }
}
