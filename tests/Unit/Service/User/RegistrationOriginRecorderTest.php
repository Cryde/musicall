<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\User;

use App\Entity\User;
use App\Enum\User\RegistrationMethod;
use App\Enum\User\RegistrationPlatform;
use App\Service\User\RegistrationOriginRecorder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class RegistrationOriginRecorderTest extends TestCase
{
    #[DataProvider('origins')]
    public function test_it_records_the_platform_and_the_version_the_headers_allow(
        ?string $platformHeader,
        ?string $versionHeader,
        RegistrationPlatform $platformWithoutHeader,
        RegistrationPlatform $expectedPlatform,
        ?string $expectedVersion,
    ): void {
        $server = array_filter(
            ['HTTP_X_CLIENT_PLATFORM' => $platformHeader, 'HTTP_X_CLIENT_VERSION' => $versionHeader],
            static fn (?string $value): bool => $value !== null,
        );
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/', server: $server));
        $user = new User();

        (new RegistrationOriginRecorder($requestStack))->record($user, RegistrationMethod::Google, $platformWithoutHeader);

        $this->assertSame(
            [RegistrationMethod::Google, $expectedPlatform, $expectedVersion],
            [$user->registrationMethod, $user->registrationPlatform, $user->registrationAppVersion],
        );
    }

    /** @return iterable<string, array{?string, ?string, RegistrationPlatform, RegistrationPlatform, ?string}> */
    public static function origins(): iterable
    {
        $web = RegistrationPlatform::Web;
        $unknownApp = RegistrationPlatform::UnknownApp;

        yield 'no header, web' => [null, null, $web, $web, null];
        yield 'no header, native route' => [null, null, $unknownApp, $unknownApp, null];
        yield 'ios with its version' => ['ios', '1.13.0', $web, RegistrationPlatform::Ios, '1.13.0'];
        yield 'android with a build number' => ['android', '1.13.0+42', $web, RegistrationPlatform::Android, '1.13.0+42'];
        yield 'android on the native route' => ['android', '2.0', $unknownApp, RegistrationPlatform::Android, '2.0'];
        yield 'android without a version' => ['android', null, $web, RegistrationPlatform::Android, null];
        yield 'a version is dropped for web' => [null, '1.13.0', $web, $web, null];
        yield 'a version is kept for an unknown app' => [null, '1.13.0', $unknownApp, $unknownApp, '1.13.0'];
        yield 'an unknown platform falls back, web' => ['windows', '1.13.0', $web, $web, null];
        yield 'an unknown platform falls back, native route' => ['windows', '1.13.0', $unknownApp, $unknownApp, '1.13.0'];
        yield 'web cannot be claimed on the native route' => ['web', null, $unknownApp, $unknownApp, null];
        yield 'unknown_app cannot be claimed' => ['unknown_app', null, $web, $web, null];
        yield 'exact lowercase only' => ['IOS', '1.13.0', $web, $web, null];
        yield 'surrounding spaces are not trimmed' => [' ios', '1.13.0', $web, $web, null];
        yield 'a version with letters' => ['ios', '1.13.0-beta', $web, RegistrationPlatform::Ios, null];
        yield 'a version with a trailing newline' => ['ios', "1.13.0\n", $web, RegistrationPlatform::Ios, null];
        yield 'a version part too long' => ['ios', '12345.0', $web, RegistrationPlatform::Ios, null];
        yield 'too many version parts' => ['ios', '1.2.3.4.5', $web, RegistrationPlatform::Ios, null];
        yield 'a build number too long' => ['ios', '1.0+12345678901', $web, RegistrationPlatform::Ios, null];
        yield 'an empty version' => ['ios', '', $web, RegistrationPlatform::Ios, null];
    }

    public function test_without_a_request_the_default_platform_is_recorded(): void
    {
        $user = new User();

        (new RegistrationOriginRecorder(new RequestStack()))->record($user, RegistrationMethod::Password);

        $this->assertSame(
            [RegistrationMethod::Password, RegistrationPlatform::Web, null],
            [$user->registrationMethod, $user->registrationPlatform, $user->registrationAppVersion],
        );
    }
}
