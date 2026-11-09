<?php declare(strict_types=1);

namespace App\Enum\User;

/** How an account was created (#1355). The Google and Apple values are the social account providers. */
enum RegistrationMethod: string
{
    case Password = 'password';
    case Google = 'google';
    case Apple = 'apple';
}
