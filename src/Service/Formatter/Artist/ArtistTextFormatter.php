<?php

namespace App\Service\Formatter\Artist;

class ArtistTextFormatter
{
    private \HTMLPurifier $artistContentPurifier;

    public function __construct(\HTMLPurifier $artistContentPurifier)
    {
        $this->artistContentPurifier = $artistContentPurifier;
    }

    public function formatNewLine(string $str): string
    {
        return $this->artistContentPurifier->purify(nl2br($str));
    }
}
