<?php

namespace App\Tests\Unit\Service\BandSpace;

use App\Service\BandSpace\ColorAssignmentService;
use PHPUnit\Framework\TestCase;

class ColorAssignmentServiceTest extends TestCase
{
    private ColorAssignmentService $service;

    protected function setUp(): void
    {
        $this->service = new ColorAssignmentService();
    }

    public function test_first_color(): void
    {
        $this->assertSame('#FF6B6B', $this->service->assignColor(0));
    }

    public function test_tenth_color(): void
    {
        $this->assertSame('#FD79A8', $this->service->assignColor(9));
    }

    public function test_wraps_around_after_ten(): void
    {
        $this->assertSame('#FF6B6B', $this->service->assignColor(10));
    }

    public function test_arbitrary_count(): void
    {
        $this->assertSame('#96CEB4', $this->service->assignColor(13));
    }
}
