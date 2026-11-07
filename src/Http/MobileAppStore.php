<?php declare(strict_types=1);

namespace App\Http;

/**
 * Where `/app`, the QR code's target, sends a phone (#1339): the store of its own platform. The same URLs as
 * `assets/js/utils/mobileApp.js`. A Mac counts as Apple because iPadOS asks for the desktop site with a Mac user
 * agent, and only the browser's touch points, which the server never sees, tell the two apart.
 */
final class MobileAppStore
{
    public const string PLAY_STORE_URL = 'https://play.google.com/store/apps/details?id=com.musicall.app';
    public const string APP_STORE_URL = 'https://apps.apple.com/fr/app/musicall-musiciens-groupes/id6828999540';

    /** The store for this user agent, or null when it is neither an Apple device nor an Android one. */
    public static function urlFor(string $userAgent): ?string
    {
        if (preg_match('/iPhone|iPad|iPod|Macintosh/', $userAgent) === 1) {
            return self::APP_STORE_URL;
        }
        if (str_contains($userAgent, 'Android')) {
            return self::PLAY_STORE_URL;
        }

        return null;
    }
}
