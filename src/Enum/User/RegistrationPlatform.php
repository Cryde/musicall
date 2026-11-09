<?php declare(strict_types=1);

namespace App\Enum\User;

/** Where an account was created (#1355). */
enum RegistrationPlatform: string
{
    case Web = 'web';
    case Ios = 'ios';
    case Android = 'android';
    // A native sign-in route whose client did not say which app it is.
    case UnknownApp = 'unknown_app';
}
