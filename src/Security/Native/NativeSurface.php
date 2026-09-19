<?php

declare(strict_types=1);

namespace App\Security\Native;

use Symfony\Component\HttpFoundation\Request;

/**
 * The endpoints that issue credentials to a native client, named once.
 *
 * Everything under this prefix obeys two rules that hold nowhere else in the application: nothing
 * reads a cookie off the request (BlindNativeSurfaceToCookiesListener) and nothing writes one to the
 * response (NativeCredentialsInBodyListener). Both are only correct while the prefix here, the
 * firewall patterns in config/packages/security.yaml and the routes in config/routes/native.yaml all
 * agree, so a new native endpoint goes under this prefix or it goes without the guard.
 *
 * Static rather than a service, like App\Http\ReturnUrl and App\Date\CalendarDay: the answer depends
 * on nothing but the request.
 */
final class NativeSurface
{
    public const string PREFIX = '/api/native/';

    public static function covers(?Request $request): bool
    {
        return $request instanceof Request && str_starts_with($request->getPathInfo(), self::PREFIX);
    }
}
