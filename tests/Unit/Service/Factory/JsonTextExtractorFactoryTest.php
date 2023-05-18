<?php

namespace App\Tests\Unit\Service\Factory;

use App\Service\Factory\JsonTextExtractorFactory;
use Cryde\JsonTxtExtractor\JsonTextExtractor;
use PHPUnit\Framework\TestCase;

class JsonTextExtractorFactoryTest extends TestCase
{
    private JsonTextExtractorFactory $jsonTextExtractorFactory;

    protected function setUp(): void
    {
        $this->jsonTextExtractorFactory = new JsonTextExtractorFactory();
        parent::setUp();
    }

    public function testCreate(): void
    {
        $this->assertInstanceOf(JsonTextExtractor::class, $this->jsonTextExtractorFactory->create());
    }
}