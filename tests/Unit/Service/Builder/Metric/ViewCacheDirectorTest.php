<?php

namespace App\Tests\Unit\Service\Builder\Metric;

use App\Service\Builder\Metric\ViewCacheDirector;
use PHPUnit\Framework\TestCase;

class ViewCacheDirectorTest extends TestCase
{
    public function test_build(): void
    {
        $result = (new ViewCacheDirector())->build();

        $this->assertSame(0, $result->getCount());
    }
}