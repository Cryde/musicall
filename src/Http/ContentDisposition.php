<?php declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Builds a Content-Disposition "attachment" header value that survives
 * non-ASCII and path-separator characters in the filename.
 *
 * Symfony's HeaderUtils::makeDisposition() throws when the fallback filename is
 * not ASCII, contains "%", or when either name contains "/" or "\". A raw
 * user-supplied name (an accented setlist title, an uploaded filename) therefore
 * produced an unmapped exception -> HTTP 500. This helper sanitises the display
 * name and derives an ASCII fallback; modern browsers still receive the real
 * name through the RFC 5987 filename* parameter.
 */
final class ContentDisposition
{
    public static function attachment(string $filename): string
    {
        return HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            str_replace(['/', '\\'], '-', $filename),
            self::asciiFallback($filename),
        );
    }

    /**
     * Slugs each dot-separated segment so the extension is preserved
     * ("Répétition.pdf" -> "Repetition.pdf", "doc.txt" -> "doc.txt").
     */
    private static function asciiFallback(string $filename): string
    {
        $slugger = new AsciiSlugger();
        $segments = array_filter(
            array_map(
                static fn (string $segment): string => $slugger->slug($segment)->toString(),
                explode('.', $filename),
            ),
            static fn (string $segment): bool => $segment !== '',
        );

        return $segments === [] ? 'file' : implode('.', $segments);
    }
}
