<?php

declare(strict_types=1);

namespace App\Service\Musician;

use App\Enum\Musician\MediaPlatform;
use App\Service\Musician\MediaUrlParser\MediaUrlParserInterface;
use App\Service\Musician\MediaUrlParser\ParsedMediaUrl;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

readonly class MediaUrlParser
{
    /**
     * @param iterable<MediaUrlParserInterface> $parsers
     */
    public function __construct(
        #[AutowireIterator('app.media_url_parser')]
        private iterable $parsers,
    ) {
    }

    /** @param list<MediaPlatform> $platforms what the link may embed: a link to any other platform is not one */
    public function parse(string $url, array $platforms = MediaPlatform::READ_BY_APP): ?ParsedMediaUrl
    {
        $url = trim($url);

        foreach ($this->parsers as $parser) {
            if ($parser->supports($url)) {
                try {
                    $parsed = $parser->parse($url);
                } catch (\InvalidArgumentException) {
                    // Parser supports URL but couldn't parse it, try next parser or return null
                    continue;
                }

                return in_array($parsed->platform, $platforms, true) ? $parsed : null;
            }
        }

        return null;
    }
}
