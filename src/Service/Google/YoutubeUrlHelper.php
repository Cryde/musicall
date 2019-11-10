<?php

namespace App\Service\Google;

class YoutubeUrlHelper
{
    /**
     * @param string $urlVideo
     *
     * @return false|mixed|string
     */
    public function getVideoId(string $urlVideo)
    {
        $host = mb_strtolower(parse_url($urlVideo, PHP_URL_HOST));
        if ($host === 'youtu.be') {
            $path = parse_url($urlVideo, PHP_URL_PATH);
            $videoId = substr($path, 1);
        } else {
            parse_str(parse_url($urlVideo, PHP_URL_QUERY), $args);
            $videoId = $args['v'] ?? '';
        }

        return $videoId;
    }
}
