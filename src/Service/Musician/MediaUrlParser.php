<?php

declare(strict_types=1);

namespace App\Service\Musician;

use App\Service\Musician\MediaUrlParser\MediaUrlParserInterface;
use App\Service\Musician\MediaUrlParser\ParsedMediaUrl;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

readonly class MediaUrlParser
{
    /**
     * @param iterable<MediaUrlParserInterface> $parsers
     */
    public function __construct(
        #[TaggedIterator('app.media_url_parser')]
        private iterable $parsers,
    ) {
    }

    public function parse(string $url): ?ParsedMediaUrl
    {
        $url = trim($url);

        foreach ($this->parsers as $parser) {
            if ($parser->supports($url)) {
                try {
                    return $parser->parse($url);
                } catch (\InvalidArgumentException) {
                    // Parser supports URL but couldn't parse it, try next parser or return null
                    continue;
                }
            }
        }

        return null;
    }
}
