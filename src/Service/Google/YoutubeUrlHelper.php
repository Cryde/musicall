<?php declare(strict_types=1);

namespace App\Service\Google;
class YoutubeUrlHelper
{
    public function getVideoId(string $urlVideo): string
    {
        preg_match('~(youtu.*be.*)\/(watch\?v=|embed\/|v|shorts|)(.*?((?=[&#?])|$))~', $urlVideo, $matches);

        return $matches[3] ?? '';
    }
}
