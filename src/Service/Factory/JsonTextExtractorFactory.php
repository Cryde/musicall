<?php

namespace App\Service\Factory;

use Cryde\JsonTxtExtractor\JsonTextExtractor;

class JsonTextExtractorFactory
{
    public function create(): JsonTextExtractor
    {
        return new JsonTextExtractor();
    }
}