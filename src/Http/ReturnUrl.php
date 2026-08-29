<?php declare(strict_types=1);

namespace App\Http;

/**
 * Whether a `return_url` can be redirected to after a login.
 *
 * The rule is the one `assets/js/utils/returnUrl.js` applies on the SPA login path, plus a branch the
 * SPA has no use for: a leading `/` that is not followed by another `/` or by a `\`, or an absolute URL
 * whose host is the frontend's own.
 *
 * The two forbidden second characters are the same trick. `//evil.example` is protocol relative, and a
 * browser normalises `\` to `/` on a special scheme, so `/\evil.example` resolves the same way. Both
 * read as an internal path and both leave the origin. An ASCII tab, CR or LF anywhere in the value is
 * the same trick once more, and the one that reads least like an attack: the URL parser deletes those
 * three characters from the whole input before it parses anything, so `/<tab>/evil.example` is handed
 * to it as `//evil.example`.
 *
 * This lives here rather than in AbstractOAuthController because the check used to accept any leading
 * `/` and was saved only by what its caller happened to do with the value, concatenating it onto a
 * fixed origin. That is protection one function away from the check, invisible to anyone reusing the
 * check for a new redirect. Written once, in a class of its own, it can also be tested as the matched
 * pair of the frontend rule, which is what keeps the two from drifting apart again.
 */
final class ReturnUrl
{
    /** The schemes a redirect may use. Anything else is somebody else's application. */
    private const array ALLOWED_SCHEMES = ['http', 'https'];

    public static function isSafe(string $url, string $frontendUrl): bool
    {
        // Refused rather than stripped and re-checked, so the string that is checked is the string that
        // gets used. No legitimate return_url carries one of these.
        if (strpbrk($url, "\t\r\n") !== false) {
            return false;
        }

        if (str_starts_with($url, '/')) {
            return !str_starts_with($url, '//') && !str_starts_with($url, '/\\');
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);
        $frontendHost = parse_url($frontendUrl, PHP_URL_HOST);

        // The host alone is not enough: parse_url reads one out of any scheme written with an authority,
        // so `javascript://musicall.fr/x` would otherwise match the frontend and be handed straight to a
        // Location header. Browsers block that particular scheme there, which is not a reason to emit it.
        return is_string($scheme)
            && in_array(strtolower($scheme), self::ALLOWED_SCHEMES, true)
            && is_string($host)
            && is_string($frontendHost)
            // A host is case insensitive, so MUSICALL.FR really is the frontend's own origin.
            && strcasecmp($host, $frontendHost) === 0;
    }
}
