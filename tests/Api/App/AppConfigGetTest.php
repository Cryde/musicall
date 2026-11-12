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
            '{"android":{"min_supported_build":1,"latest_build":1},"ios":{"min_supported_build":1,"latest_build":1}}',
            (string) $this->client->getResponse()->getContent(),
        );
        $this->assertJsonEquals([
            'android' => ['min_supported_build' => 1, 'latest_build' => 1],
            'ios' => ['min_supported_build' => 1, 'latest_build' => 1],
        ]);
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
        $this->assertJsonEquals([
            'android' => ['min_supported_build' => 1, 'latest_build' => 1],
            'ios' => ['min_supported_build' => 1, 'latest_build' => 1],
        ]);
    }

    /** @return iterable<string, array{array<string, string>, array<string, array<string, int>>}> */
    public static function environments(): iterable
    {
        yield 'Android raised, iOS left alone' => [
            ['APP_ANDROID_MIN_SUPPORTED_BUILD' => '12', 'APP_ANDROID_LATEST_BUILD' => '15'],
            [
                'android' => ['min_supported_build' => 12, 'latest_build' => 15],
                'ios' => ['min_supported_build' => 1, 'latest_build' => 1],
            ],
        ];
        yield 'iOS raised, Android left alone' => [
            ['APP_IOS_MIN_SUPPORTED_BUILD' => '12', 'APP_IOS_LATEST_BUILD' => '14'],
            [
                'android' => ['min_supported_build' => 1, 'latest_build' => 1],
                'ios' => ['min_supported_build' => 12, 'latest_build' => 14],
            ],
        ];
        yield 'both raised, each to its own builds' => [
            [
                'APP_ANDROID_MIN_SUPPORTED_BUILD' => '12',
                'APP_ANDROID_LATEST_BUILD' => '15',
                'APP_IOS_MIN_SUPPORTED_BUILD' => '11',
                'APP_IOS_LATEST_BUILD' => '14',
            ],
            [
                'android' => ['min_supported_build' => 12, 'latest_build' => 15],
                'ios' => ['min_supported_build' => 11, 'latest_build' => 14],
            ],
        ];
    }

    /**
     * Raising the builds is a line in the server's environment, read through to the response.
     *
     * @param array<string, string> $environment
     * @param array<string, array<string, int>> $expected
     */
    #[DataProvider('environments')]
    public function test_the_builds_come_from_the_environment(array $environment, array $expected): void
    {
        foreach ($environment as $name => $value) {
            $_SERVER[$name] = $_ENV[$name] = $value;
        }
        try {
            $this->client->request('GET', '/api/app/config');
        } finally {
            foreach (array_keys($environment) as $name) {
                unset($_SERVER[$name], $_ENV[$name]);
            }
        }

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals($expected);
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
