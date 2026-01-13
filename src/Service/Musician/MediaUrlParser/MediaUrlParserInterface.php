<?php

declare(strict_types=1);

namespace App\Service\Musician\MediaUrlParser;

interface MediaUrlParserInterface
{
    public function supports(string $url): bool;

    public function parse(string $url): ParsedMediaUrl;
}
