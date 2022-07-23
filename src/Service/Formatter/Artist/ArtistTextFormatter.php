<?php

namespace App\Service\Formatter\Artist;

use HtmlSanitizer\SanitizerInterface;

class ArtistTextFormatter
{
    public function __construct(private readonly SanitizerInterface $sanitizer)
    {
    }

    public function formatNewLine(string $str): string
    {
        return $this->sanitizer->sanitize(nl2br($str));
    }
}
