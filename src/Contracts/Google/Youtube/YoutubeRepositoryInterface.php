<?php declare(strict_types=1);

namespace App\Contracts\Google\Youtube;
interface YoutubeRepositoryInterface
{
    /**
     * @return array{title: string, description: string, thumbnails: array<string, string>}|null
     */
    public function fetchVideoData(string $videoId): ?array;
}
