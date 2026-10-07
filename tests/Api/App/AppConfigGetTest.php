<?php

declare(strict_types=1);

namespace App\Tests\Api\App;

use App\Tests\ApiTestAssertionsTrait;
use App\Tests\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/** The body every build already on a phone reads on launch (#1132): any change to it breaks them. */
class AppConfigGetTest extends ApiTestCase
{
    use ApiTestAssertionsTrait;

    /** @return iterable<string, array{string}> */
    public static function acceptedTypes(): iterable
    {
        // JSON-LD is what the app's client asks for on every request.
        yield 'JSON-LD, as the app asks' => ['application/ld+json'];
        yield 'plain JSON' => ['application/json'];
        yield 'anything' => ['*/*'];
    }

    #[DataProvider('acceptedTypes')]
    public function test_answers_signed_out_with_the_contract_body(string $accept): void
    {
        $this->client->request('GET', '/api/app/config', [], [], ['HTTP_ACCEPT' => $accept]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
        $this->assertSame(
            '{"android":{"min_supported_build":1,"latest_build":1}}',
            (string) $this->client->getResponse()->getContent(),
        );
        $this->assertJsonEquals(['android' => ['min_supported_build' => 1, 'latest_build' => 1]]);
    }

    /** @return iterable<string, array{array<string, string>}> */
    public static function staleCredentials(): iterable
    {
        yield 'an expired or garbage Bearer token, as the app may send' => [['HTTP_AUTHORIZATION' => 'Bearer not.a.valid-token']];
        yield 'stale split JWT cookies, as a browser may send' => [['HTTP_COOKIE' => 'jwt_hp=a.b; jwt_s=c']];
    }

    /**
     * A phone launching with an expired token is exactly who this endpoint exists for, so whatever
     * credentials come along, it answers.
     *
     * @param array<string, string> $server
     */
    #[DataProvider('staleCredentials')]
    public function test_stale_credentials_do_not_stop_it(array $server): void
    {
        $this->client->request('GET', '/api/app/config', [], [], $server + ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals(['android' => ['min_supported_build' => 1, 'latest_build' => 1]]);
    }

    /** Raising the builds is a line in the server's environment, read through to the response. */
    public function test_the_builds_come_from_the_environment(): void
    {
        $_SERVER['APP_ANDROID_MIN_SUPPORTED_BUILD'] = $_ENV['APP_ANDROID_MIN_SUPPORTED_BUILD'] = '12';
        $_SERVER['APP_ANDROID_LATEST_BUILD'] = $_ENV['APP_ANDROID_LATEST_BUILD'] = '15';
        try {
            $this->client->request('GET', '/api/app/config');
        } finally {
            unset($_SERVER['APP_ANDROID_MIN_SUPPORTED_BUILD'], $_ENV['APP_ANDROID_MIN_SUPPORTED_BUILD'], $_SERVER['APP_ANDROID_LATEST_BUILD'], $_ENV['APP_ANDROID_LATEST_BUILD']);
        }

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals(['android' => ['min_supported_build' => 12, 'latest_build' => 15]]);
    }

    public function test_an_unsupported_type_is_refused(): void
    {
        $this->client->request('GET', '/api/app/config', [], [], ['HTTP_ACCEPT' => 'text/html']);

        $this->assertResponseStatusCodeSame(406);
    }

    public function test_is_cacheable_for_five_minutes(): void
    {
        $this->client->request('GET', '/api/app/config');

        $cacheControl = (string) $this->client->getResponse()->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=300', $cacheControl);
    }
}
