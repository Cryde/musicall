<?php

namespace App\Service\Formatter\Artist;

use HtmlSanitizer\SanitizerInterface;

class ArtistTextFormatter
{
    private SanitizerInterface $sanitizer;

    public function __construct(SanitizerInterface $onlyBr)
    {
        $this->sanitizer = $onlyBr;
    }

    public function formatNewLine(string $str): string
    {
        return $this->sanitizer->sanitize(nl2br($str));
    }
}
