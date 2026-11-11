<?php declare(strict_types=1);

namespace App\Enum\User;

/** Where a request comes from: where an account was created (#1355), and what a member uses (#1370). */
enum ClientPlatform: string
{
    case Web = 'web';
    case Ios = 'ios';
    case Android = 'android';
    // An app that did not say which one it is: a native sign-in route, or a build older than the headers.
    case UnknownApp = 'unknown_app';
}
