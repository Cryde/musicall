<?php

declare(strict_types=1);

namespace App\Mercure;

/**
 * The Mercure topics the application publishes on and hands out subscriber tokens for.
 *
 * These strings are the security boundary, not a naming convention. Caddy's own handler terminates
 * `/.well-known/mercure` and never forwards it to PHP, so no firewall, voter or checker in this
 * application is on the path of a subscription: what a subscriber may read is decided entirely by
 * the topic selectors inside the token they present. A publisher and a subscriber that disagree by
 * one character silently deliver nothing, and one that is accidentally broadened delivers somebody
 * else's mail, so the string is written once, here. Once per language, strictly: the browser builds
 * the same topic in `assets/js/utils/notificationStream.js`, and the two have to be changed together.
 *
 * Two things have to be true for a per-user topic to actually be private, and only one of them lives
 * in this file. The token must name the topic (that is the cookie issued by MercureSubscriberCookie),
 * and the update must be published with `private: true`: the hub only consults a subscriber's
 * selectors for private updates, so a public update on a user's topic reaches anybody holding any
 * valid token who asks for it.
 */
final class MercureTopic
{
    /**
     * Everything addressed to one user: the notification bell first, and whatever else later wants
     * to reach a signed-in person rather than a page they happen to have open.
     *
     * A Band Space channel rides this too rather than having a topic of its own, and deliberately:
     * a per-space topic would have to be named in the subscriber token, so the token would need
     * reissuing every time a roster changed. See MessagePostedListener (#963).
     */
    public static function userNotifications(string $userId): string
    {
        return '/users/' . $userId . '/notifications';
    }
}
