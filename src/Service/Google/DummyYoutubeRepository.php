<?php

declare(strict_types=1);

namespace App\Service\Google;

use App\Contracts\Google\Youtube\YoutubeRepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\When;

#[When('test')]
readonly class DummyYoutubeRepository implements YoutubeRepositoryInterface
{
    public const string VIDEO_ID_RICK_ASTLEY = 'dQw4w9WgXcQ';
    public const string VIDEO_ID_SHORT_VIDEO = 'shortVidId';
    public const string VIDEO_ID_NON_EXISTING = 'nonExistingId';
    public const string VIDEO_ID_PROCEDURE_TEST = 'YudHcBIxlYw';

    /**
     * @var array<string, array{title: string, description: string, thumbnails: array<string, string>}>
     */
    private const array VIDEO_DATA = [
        self::VIDEO_ID_RICK_ASTLEY => [
            'title' => 'Never Gonna Give You Up',
            'description' => 'The official video for Rick Astley',
            'thumbnails' => [
                'maxres' => 'https://i.ytimg.com/vi/dQw4w9WgXcQ/maxresdefault.jpg',
                'high' => 'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg',
            ],
        ],
        self::VIDEO_ID_SHORT_VIDEO => [
            'title' => 'Short Video',
            'description' => 'A short video description',
            'thumbnails' => [
                'maxres' => 'https://i.ytimg.com/vi/shortVidId/maxresdefault.jpg',
                'high' => 'https://i.ytimg.com/vi/shortVidId/hqdefault.jpg',
            ],
        ],
        self::VIDEO_ID_PROCEDURE_TEST => [
            'title' => 'titre de la vidéo',
            'description' => 'description de la vidéo',
            'thumbnails' => [
                'maxres' => 'max_res_url',
                'high' => 'high_res_url',
            ],
        ],
    ];

    /**
     * @return array{title: string, description: string, thumbnails: array<string, string>}|null
     */
    public function fetchVideoData(string $videoId): ?array
    {
        return self::VIDEO_DATA[$videoId] ?? null;
    }
}
