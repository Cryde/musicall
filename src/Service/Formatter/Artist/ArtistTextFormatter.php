<?php

namespace App\Service\Formatter\Artist;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

class ArtistTextFormatter
{
    public function __construct(private readonly HtmlSanitizerInterface $sanitizer)
    {
    }

    public function formatNewLine(string $str): string
    {
        return $this->sanitizer->sanitize(nl2br($str));
    }
}
