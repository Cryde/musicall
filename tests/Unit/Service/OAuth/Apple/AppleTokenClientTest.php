<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\OAuth\Apple;

use App\Service\OAuth\Apple\AppleClientSecret;
use App\Service\OAuth\Apple\AppleTokenClient;
use App\Service\OAuth\Apple\AppleTokenRequestException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * The code exchange against a mocked Apple token endpoint, with a client secret signed by a throwaway
 * .p8 key and checked here against its public half (#1269).
 */
class AppleTokenClientTest extends TestCase
{
    private const string CLIENT_ID = 'com.musicall.app';
    private const string TEAM_ID = 'TEAM123456';
    private const string KEY_ID = 'KEY1234567';

    private static string $privateKeyPath;
    private static string $publicKey;

    /** @var list<array{method: string, url: string, body: array<mixed>}> */
    private array $requests = [];

    public static function setUpBeforeClass(): void
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        self::assertNotFalse($key);
        openssl_pkey_export($key, $privateKey);
        self::$privateKeyPath = (string) tempnam(sys_get_temp_dir(), 'apple_p8_');
        file_put_contents(self::$privateKeyPath, $privateKey);
        $details = openssl_pkey_get_details($key);
        self::assertIsArray($details);
        self::$publicKey = $details['key'];
    }

    public static function tearDownAfterClass(): void
    {
        unlink(self::$privateKeyPath);
    }

    public function test_a_code_is_exchanged_for_the_refresh_token_and_its_subject(): void
    {
        $tokens = $this->client(new MockResponse((string) json_encode([
            'access_token' => 'an-access-token',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'refresh_token' => 'r.apple-refresh-token',
            'id_token' => JWT::encode(['sub' => '001234.abcdef.1234'], 'unchecked-signature-long-enough-key', 'HS256'),
        ])))->exchangeAuthorizationCode('the-code', self::CLIENT_ID);

        $this->assertSame('r.apple-refresh-token', $tokens->refreshToken);
        $this->assertSame('001234.abcdef.1234', $tokens->subject);

        $this->assertCount(1, $this->requests);
        $request = $this->requests[0];
        $this->assertSame('POST', $request['method']);
        $this->assertSame('https://appleid.apple.com/auth/token', $request['url']);
        $clientSecret = $request['body']['client_secret'];
        unset($request['body']['client_secret']);
        $this->assertSame(['client_id' => self::CLIENT_ID, 'code' => 'the-code', 'grant_type' => 'authorization_code'], $request['body']);

        $this->assertIsString($clientSecret);
        // The library fills the headers in only when handed an object to fill.
        $headers = new \stdClass();
        $claims = (array) JWT::decode($clientSecret, new Key(self::$publicKey, 'ES256'), $headers);
        $this->assertSame(self::KEY_ID, $headers->kid);
        $this->assertSame(self::TEAM_ID, $claims['iss']);
        $this->assertSame('https://appleid.apple.com', $claims['aud']);
        $this->assertSame(self::CLIENT_ID, $claims['sub']);
        $this->assertSame(300, $claims['exp'] - $claims['iat']);
    }

    public function test_apple_refusing_the_code_names_its_reason(): void
    {
        $this->expectException(AppleTokenRequestException::class);
        $this->expectExceptionMessage('Apple refused the request: invalid_grant');

        $this->client(new MockResponse('{"error": "invalid_grant"}', ['http_code' => 400]))->exchangeAuthorizationCode('a-used-code', self::CLIENT_ID);
    }

    public function test_apple_failing_without_a_reason_names_the_status(): void
    {
        $this->expectException(AppleTokenRequestException::class);
        $this->expectExceptionMessage('Apple refused the request: 503');

        $this->client(new MockResponse('Service Unavailable', ['http_code' => 503]))->exchangeAuthorizationCode('the-code', self::CLIENT_ID);
    }

    public function test_apple_out_of_reach_is_a_failed_request(): void
    {
        $this->expectException(AppleTokenRequestException::class);
        $this->expectExceptionMessage('Apple could not be reached');

        $this->client(new TransportException('Connection refused'))->exchangeAuthorizationCode('the-code', self::CLIENT_ID);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function incompleteAnswers(): iterable
    {
        yield 'no refresh token' => [['id_token' => 'a.b.c']];
        yield 'no id token' => [['refresh_token' => 'r.token']];
        yield 'an unreadable id token' => [['refresh_token' => 'r.token', 'id_token' => 'not-a-jwt']];
        yield 'an id token without a subject' => [['refresh_token' => 'r.token', 'id_token' => JWT::encode(['aud' => 'x'], 'unchecked-signature-long-enough-key', 'HS256')]];
    }

    /** @param array<string, mixed> $answer */
    #[DataProvider('incompleteAnswers')]
    public function test_an_incomplete_answer_is_a_failed_request(array $answer): void
    {
        $this->expectException(AppleTokenRequestException::class);

        $this->client(new MockResponse((string) json_encode($answer)))->exchangeAuthorizationCode('the-code', self::CLIENT_ID);
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function missingCredentials(): iterable
    {
        yield 'no team id' => ['', self::KEY_ID, 'private-key'];
        yield 'no key id' => [self::TEAM_ID, '', 'private-key'];
        yield 'no private key' => [self::TEAM_ID, self::KEY_ID, ''];
        yield 'a private key that does not exist' => [self::TEAM_ID, self::KEY_ID, '/nonexistent/AuthKey.p8'];
    }

    /** A server without the Apple credentials fails the request without calling Apple. */
    #[DataProvider('missingCredentials')]
    public function test_missing_credentials_fail_before_calling_apple(string $teamId, string $keyId, string $privateKeyPath): void
    {
        $client = new AppleTokenClient(
            new MockHttpClient(fn () => throw new \LogicException('Apple must not be called')),
            new AppleClientSecret($teamId, $keyId, $privateKeyPath === 'private-key' ? self::$privateKeyPath : $privateKeyPath),
        );

        $this->expectException(AppleTokenRequestException::class);

        $client->exchangeAuthorizationCode('the-code', self::CLIENT_ID);
    }

    public function test_a_key_that_cannot_sign_fails_before_calling_apple(): void
    {
        $notAKey = (string) tempnam(sys_get_temp_dir(), 'apple_p8_');
        file_put_contents($notAKey, 'not a key');
        $client = new AppleTokenClient(
            new MockHttpClient(fn () => throw new \LogicException('Apple must not be called')),
            new AppleClientSecret(self::TEAM_ID, self::KEY_ID, $notAKey),
        );

        try {
            $this->expectException(AppleTokenRequestException::class);
            $client->exchangeAuthorizationCode('the-code', self::CLIENT_ID);
        } finally {
            unlink($notAKey);
        }
    }

    private function client(MockResponse|\Throwable $answer): AppleTokenClient
    {
        $http = new MockHttpClient(function (string $method, string $url, array $options) use ($answer): MockResponse {
            parse_str($options['body'], $body);
            $this->requests[] = ['method' => $method, 'url' => $url, 'body' => $body];
            if ($answer instanceof \Throwable) {
                throw $answer;
            }

            return $answer;
        }, 'https://appleid.apple.com/');

        return new AppleTokenClient($http, new AppleClientSecret(self::TEAM_ID, self::KEY_ID, self::$privateKeyPath));
    }
}
