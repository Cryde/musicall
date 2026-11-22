<?php

declare(strict_types=1);

namespace App\Service\Musician\MediaUrlParser;

use App\Enum\Musician\MediaPlatform;

/**
 * A Vimeo video (#1411), on Vimeo's own hosts only: a lookalike host, or `vimeo.com` written in another site's path or
 * query, is not one. An unlisted video only plays with its hash, so the embed id keeps it as Vimeo's own link does:
 * `123456789/abcdef1234`.
 */
readonly class VimeoUrlParser implements MediaUrlParserInterface
{
    private const array HOSTS = ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'];
    private const string PLAYER_HOST = 'player.vimeo.com';

    public function supports(string $url): bool
    {
        return $this->parts($url) !== null;
    }

    public function parse(string $url): ParsedMediaUrl
    {
        $parts = $this->parts($url) ?? throw new \InvalidArgumentException('Not a Vimeo URL');

        // player.vimeo.com/video/VIDEO_ID, with its ?h=HASH when unlisted
        if ($parts['host'] === self::PLAYER_HOST && preg_match('#^/video/(\d+)/?$#', $parts['path'], $matches)) {
            parse_str($parts['query'], $query);
            $hash = $query['h'] ?? null;

            return new ParsedMediaUrl(MediaPlatform::VIMEO, $this->embedId($matches[1], is_string($hash) && preg_match('/^[0-9a-f]+$/i', $hash) ? $hash : null));
        }

        // vimeo.com/VIDEO_ID or vimeo.com/VIDEO_ID/HASH, also inside a channel, a group or a showcase
        if ($parts['host'] !== self::PLAYER_HOST && preg_match('#^/(?:channels/[\w-]+/|groups/[\w-]+/videos/|showcase/\d+/video/)?(\d+)(?:/([0-9a-f]+))?/?$#i', $parts['path'], $matches)) {
            return new ParsedMediaUrl(MediaPlatform::VIMEO, $this->embedId($matches[1], $matches[2] ?? null));
        }

        throw new \InvalidArgumentException('Invalid Vimeo URL');
    }

    /**
     * The host and what follows it, for an http or https address on one of Vimeo's hosts, without a user or a port.
     *
     * @return array{host: string, path: string, query: string}|null
     */
    private function parts(string $url): ?array
    {
        $parts = parse_url($url);
        if (!is_array($parts) || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            return null;
        }
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower($parts['host'] ?? '');
        if (!in_array($scheme, ['http', 'https'], true) || !in_array($host, self::HOSTS, true)) {
            return null;
        }

        return ['host' => $host, 'path' => $parts['path'] ?? '', 'query' => $parts['query'] ?? ''];
    }

    private function embedId(string $id, ?string $hash): string
    {
        return $hash !== null && $hash !== '' ? $id . '/' . strtolower($hash) : $id;
    }
}
