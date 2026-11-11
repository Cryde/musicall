<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\User;

use App\Entity\User;
use App\Enum\User\ClientPlatform;
use App\Enum\User\RegistrationMethod;
use App\Service\User\RegistrationCampaign;
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
        ClientPlatform $platformWithoutHeader,
        ClientPlatform $expectedPlatform,
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

    /** @return iterable<string, array{?string, ?string, ClientPlatform, ClientPlatform, ?string}> */
    public static function origins(): iterable
    {
        $web = ClientPlatform::Web;
        $unknownApp = ClientPlatform::UnknownApp;

        yield 'no header, web' => [null, null, $web, $web, null];
        yield 'no header, native route' => [null, null, $unknownApp, $unknownApp, null];
        yield 'ios with its version' => ['ios', '1.13.0', $web, ClientPlatform::Ios, '1.13.0'];
        yield 'android with a build number' => ['android', '1.13.0+42', $web, ClientPlatform::Android, '1.13.0+42'];
        yield 'android on the native route' => ['android', '2.0', $unknownApp, ClientPlatform::Android, '2.0'];
        yield 'android without a version' => ['android', null, $web, ClientPlatform::Android, null];
        yield 'a version is dropped for web' => [null, '1.13.0', $web, $web, null];
        yield 'a version is kept for an unknown app' => [null, '1.13.0', $unknownApp, $unknownApp, '1.13.0'];
        yield 'an unknown platform falls back, web' => ['windows', '1.13.0', $web, $web, null];
        yield 'an unknown platform falls back, native route' => ['windows', '1.13.0', $unknownApp, $unknownApp, '1.13.0'];
        yield 'web cannot be claimed on the native route' => ['web', null, $unknownApp, $unknownApp, null];
        yield 'unknown_app cannot be claimed' => ['unknown_app', null, $web, $web, null];
        yield 'exact lowercase only' => ['IOS', '1.13.0', $web, $web, null];
        yield 'surrounding spaces are not trimmed' => [' ios', '1.13.0', $web, $web, null];
        yield 'a version with letters' => ['ios', '1.13.0-beta', $web, ClientPlatform::Ios, null];
        yield 'a version with a trailing newline' => ['ios', "1.13.0\n", $web, ClientPlatform::Ios, null];
        yield 'a version part too long' => ['ios', '12345.0', $web, ClientPlatform::Ios, null];
        yield 'too many version parts' => ['ios', '1.2.3.4.5', $web, ClientPlatform::Ios, null];
        yield 'a build number too long' => ['ios', '1.0+12345678901', $web, ClientPlatform::Ios, null];
        yield 'an empty version' => ['ios', '', $web, ClientPlatform::Ios, null];
    }

    public function test_a_web_sign_up_records_its_campaign(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/'));
        $user = new User();
        $campaign = RegistrationCampaign::fromFields(['utm_source' => 'google', 'utm_medium' => 'cpc', 'utm_campaign' => 'pmax-musiciens', 'referrer_host' => 'www.google.com']);

        (new RegistrationOriginRecorder($requestStack))->record($user, RegistrationMethod::Password, campaign: $campaign);

        $this->assertSame(
            ['google', 'cpc', 'pmax-musiciens', 'www.google.com'],
            [$user->registrationUtmSource, $user->registrationUtmMedium, $user->registrationUtmCampaign, $user->registrationReferrerHost],
        );
    }

    /** The apps measure no campaign (#1369), whatever they send. */
    #[DataProvider('appOrigins')]
    public function test_an_app_sign_up_records_no_campaign(array $server, ClientPlatform $platformWithoutHeader): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/', server: $server));
        $user = new User();
        $campaign = RegistrationCampaign::fromFields(['utm_source' => 'google', 'utm_medium' => 'cpc', 'utm_campaign' => 'pmax-musiciens', 'referrer_host' => 'www.google.com']);

        (new RegistrationOriginRecorder($requestStack))->record($user, RegistrationMethod::Google, $platformWithoutHeader, $campaign);

        $this->assertSame(
            [null, null, null, null],
            [$user->registrationUtmSource, $user->registrationUtmMedium, $user->registrationUtmCampaign, $user->registrationReferrerHost],
        );
    }

    /** @return iterable<string, array{array<string, string>, ClientPlatform}> */
    public static function appOrigins(): iterable
    {
        yield 'ios' => [['HTTP_X_CLIENT_PLATFORM' => 'ios'], ClientPlatform::Web];
        yield 'android' => [['HTTP_X_CLIENT_PLATFORM' => 'android'], ClientPlatform::Web];
        yield 'an unidentified app' => [[], ClientPlatform::UnknownApp];
    }

    public function test_without_a_request_the_default_platform_is_recorded(): void
    {
        $user = new User();

        (new RegistrationOriginRecorder(new RequestStack()))->record($user, RegistrationMethod::Password);

        $this->assertSame(
            [RegistrationMethod::Password, ClientPlatform::Web, null],
            [$user->registrationMethod, $user->registrationPlatform, $user->registrationAppVersion],
        );
    }
}
