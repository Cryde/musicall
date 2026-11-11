<?php

declare(strict_types=1);

namespace App\Tests\Unit\Http;

use App\Enum\User\ClientPlatform;
use App\Http\ClientHeaders;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/** The header parsing itself is pinned case by case by RegistrationOriginRecorderTest. */
class ClientHeadersTest extends TestCase
{
    /** @param array<string, string> $server */
    #[DataProvider('signedInRequests')]
    public function test_a_signed_in_request_is_on_the_platform_its_headers_and_token_show(array $server, ClientPlatform $expectedPlatform, ?string $expectedVersion): void
    {
        $client = ClientHeaders::of(Request::create('/', server: $server));
        $platform = $client->signedInPlatform();

        $this->assertSame([$expectedPlatform, $expectedVersion], [$platform, $client->versionFor($platform)]);
    }

    /** @return iterable<string, array{array<string, string>, ClientPlatform, ?string}> */
    public static function signedInRequests(): iterable
    {
        yield 'the web, signed in with cookies' => [[], ClientPlatform::Web, null];
        yield 'ios with its version' => [['HTTP_AUTHORIZATION' => 'Bearer a.b.c', 'HTTP_X_CLIENT_PLATFORM' => 'ios', 'HTTP_X_CLIENT_VERSION' => '1.13.0'], ClientPlatform::Ios, '1.13.0'];
        yield 'android without its token still names itself' => [['HTTP_X_CLIENT_PLATFORM' => 'android', 'HTTP_X_CLIENT_VERSION' => '1.13.0+42'], ClientPlatform::Android, '1.13.0+42'];
        yield 'a token without the headers is an app older than them' => [['HTTP_AUTHORIZATION' => 'Bearer a.b.c'], ClientPlatform::UnknownApp, null];
        yield 'the token scheme is case insensitive' => [['HTTP_AUTHORIZATION' => 'bearer a.b.c'], ClientPlatform::UnknownApp, null];
        yield 'an older app keeps a version it sends' => [['HTTP_AUTHORIZATION' => 'Bearer a.b.c', 'HTTP_X_CLIENT_VERSION' => '1.12.0'], ClientPlatform::UnknownApp, '1.12.0'];
        yield 'an unknown platform with a token' => [['HTTP_AUTHORIZATION' => 'Bearer a.b.c', 'HTTP_X_CLIENT_PLATFORM' => 'windows'], ClientPlatform::UnknownApp, null];
        yield 'the web cannot claim a version' => [['HTTP_X_CLIENT_VERSION' => '1.13.0'], ClientPlatform::Web, null];
        yield 'claiming unknown_app is not naming an app' => [['HTTP_X_CLIENT_PLATFORM' => 'unknown_app'], ClientPlatform::Web, null];
        yield 'another authorization scheme is not a token' => [['HTTP_AUTHORIZATION' => 'Basic dXNlcjpwYXNz'], ClientPlatform::Web, null];
        yield 'an invalid version is dropped' => [['HTTP_X_CLIENT_PLATFORM' => 'ios', 'HTTP_X_CLIENT_VERSION' => '1.13.0-beta'], ClientPlatform::Ios, null];
    }

    public function test_without_a_request_it_is_the_web_or_the_given_default(): void
    {
        $client = ClientHeaders::of(null);

        $this->assertSame(
            [ClientPlatform::Web, ClientPlatform::UnknownApp, null],
            [$client->signedInPlatform(), $client->namedAppOr(ClientPlatform::UnknownApp), $client->versionFor(ClientPlatform::UnknownApp)],
        );
    }
}
