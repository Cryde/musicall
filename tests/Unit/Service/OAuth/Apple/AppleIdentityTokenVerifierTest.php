<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\OAuth\Apple;

use App\Service\OAuth\Apple\AppleIdentityTokenUnverifiableException;
use App\Service\OAuth\Apple\AppleIdentityTokenVerifier;
use App\Service\OAuth\Apple\InvalidAppleIdentityTokenException;
use Firebase\JWT\JWT;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * The real check, against tokens signed here with throwaway keys that Apple's key endpoint is mocked
 * to publish (#1269). What Apple documents as refused is refused, and each failure lands on the right
 * side: the token's fault (401) or Apple's (503).
 */
class AppleIdentityTokenVerifierTest extends TestCase
{
    private const string CLIENT_ID = 'com.musicall.app';
    private const string KEY_ID = 'apple-key-1';
    private const string ROTATED_KEY_ID = 'apple-key-2';

    /** @var array<string, array{private: string, public: string, n: string, e: string}> */
    private static array $keys = [];

    /** @var list<string> */
    private array $requestedUrls = [];

    public static function setUpBeforeClass(): void
    {
        self::$keys = [self::KEY_ID => self::rsaKey(), self::ROTATED_KEY_ID => self::rsaKey()];
    }

    public function test_a_genuine_token_gives_its_claims(): void
    {
        $claims = $this->verifier()->verify($this->token(), self::CLIENT_ID);

        $this->assertSame('001234.abcdef.1234', $claims['sub']);
        $this->assertSame('alice@privaterelay.appleid.com', $claims['email']);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function refusedClaims(): iterable
    {
        yield 'another app\'s client' => [['aud' => 'com.someone.else']];
        yield 'no audience at all' => [['aud' => null]];
        yield 'not issued by Apple' => [['iss' => 'https://evil.example.com']];
        yield 'expired' => [['exp' => time() - 3600, 'iat' => time() - 7200]];
        yield 'no expiry' => [['exp' => null]];
    }

    /** @param array<string, mixed> $override */
    #[DataProvider('refusedClaims')]
    public function test_a_token_apple_would_refuse_is_invalid(array $override): void
    {
        $this->expectException(InvalidAppleIdentityTokenException::class);

        $this->verifier()->verify($this->token($override), self::CLIENT_ID);
    }

    public function test_a_token_signed_with_another_key_is_invalid(): void
    {
        $forged = JWT::encode($this->claims(), self::rsaKey()['private'], 'RS256', self::KEY_ID);

        $this->expectException(InvalidAppleIdentityTokenException::class);

        $this->verifier()->verify($forged, self::CLIENT_ID);
    }

    /** @return iterable<string, array{string}> */
    public static function notTokens(): iterable
    {
        yield 'not a JWT' => ['not-a-jwt'];
        yield 'an unreadable header' => ['!!!.e30.sig'];
        yield 'no key id' => [JWT::encode(['sub' => '1'], 'secret-long-enough-for-hs256-signing', 'HS256')];
    }

    #[DataProvider('notTokens')]
    public function test_something_that_is_not_an_apple_token_is_invalid(string $token): void
    {
        $this->expectException(InvalidAppleIdentityTokenException::class);

        $this->verifier()->verify($token, self::CLIENT_ID);
    }

    /** Apple's keys are fetched once, not on every sign-in. */
    public function test_the_keys_are_cached(): void
    {
        $verifier = $this->verifier();

        $verifier->verify($this->token(), self::CLIENT_ID);
        $verifier->verify($this->token(), self::CLIENT_ID);

        $this->assertSame(['https://appleid.apple.com/auth/keys'], $this->requestedUrls);
    }

    /** A key id the cached set does not know refetches once, since Apple may have rotated its keys. */
    public function test_a_rotated_key_is_fetched_again(): void
    {
        $verifier = $this->verifier([[self::KEY_ID], [self::KEY_ID, self::ROTATED_KEY_ID]]);
        $verifier->verify($this->token(), self::CLIENT_ID);

        $claims = $verifier->verify($this->token(keyId: self::ROTATED_KEY_ID), self::CLIENT_ID);

        $this->assertSame('001234.abcdef.1234', $claims['sub']);
        $this->assertCount(2, $this->requestedUrls);
    }

    public function test_a_key_apple_does_not_publish_is_invalid(): void
    {
        $this->expectException(InvalidAppleIdentityTokenException::class);

        $this->verifier([[self::KEY_ID], [self::KEY_ID]])->verify($this->token(keyId: self::ROTATED_KEY_ID), self::CLIENT_ID);
    }

    /** A forged key id cannot evict the keys every genuine sign-in still needs. */
    public function test_a_failed_refetch_keeps_the_cached_keys(): void
    {
        $verifier = $this->verifier([[self::KEY_ID], new MockResponse('', ['http_code' => 503])]);
        $verifier->verify($this->token(), self::CLIENT_ID);
        try {
            $verifier->verify($this->token(keyId: self::ROTATED_KEY_ID), self::CLIENT_ID);
            $this->fail('An unknown key id with Apple failing cannot be verified');
        } catch (AppleIdentityTokenUnverifiableException) {
        }

        $claims = $verifier->verify($this->token(), self::CLIENT_ID);

        $this->assertSame('001234.abcdef.1234', $claims['sub']);
        $this->assertCount(2, $this->requestedUrls);
    }

    /** A token naming a published key under another algorithm, HS256 keyed with the public key, is refused. */
    public function test_a_token_under_another_algorithm_is_invalid(): void
    {
        $confused = JWT::encode($this->claims(), self::$keys[self::KEY_ID]['public'], 'HS256', self::KEY_ID);

        $this->expectException(InvalidAppleIdentityTokenException::class);

        $this->verifier()->verify($confused, self::CLIENT_ID);
    }

    public function test_apple_answering_with_an_error_is_unverifiable(): void
    {
        $this->expectException(AppleIdentityTokenUnverifiableException::class);

        $this->verifier([new MockResponse('', ['http_code' => 503])])->verify($this->token(), self::CLIENT_ID);
    }

    public function test_apple_out_of_reach_is_unverifiable(): void
    {
        $this->expectException(AppleIdentityTokenUnverifiableException::class);

        $this->verifier([new TransportException('Connection refused')])->verify($this->token(), self::CLIENT_ID);
    }

    public function test_a_broken_key_set_is_unverifiable(): void
    {
        $this->expectException(AppleIdentityTokenUnverifiableException::class);

        $this->verifier([new MockResponse('{"keys": []}')])->verify($this->token(), self::CLIENT_ID);
    }

    /** An unset client id would accept a token for any app, so nothing is accepted. */
    public function test_without_a_client_id_nothing_is_accepted(): void
    {
        $this->expectException(AppleIdentityTokenUnverifiableException::class);

        $this->verifier()->verify($this->token(['aud' => '']), '');
    }

    /**
     * @param list<list<string>|MockResponse|\Throwable> $answers each a list of the key ids to publish, or a raw answer
     */
    private function verifier(array $answers = [[self::KEY_ID]]): AppleIdentityTokenVerifier
    {
        $client = new MockHttpClient(function (string $method, string $url) use (&$answers): MockResponse {
            $this->requestedUrls[] = $url;
            $answer = array_shift($answers) ?? throw new \LogicException('Unexpected request to ' . $url);
            if ($answer instanceof \Throwable) {
                throw $answer;
            }

            return $answer instanceof MockResponse ? $answer : new MockResponse((string) json_encode(['keys' => array_map(
                static fn (string $keyId): array => [
                    'kty' => 'RSA',
                    'kid' => $keyId,
                    'use' => 'sig',
                    'alg' => 'RS256',
                    'n' => self::$keys[$keyId]['n'],
                    'e' => self::$keys[$keyId]['e'],
                ],
                $answer,
            )]));
        }, 'https://appleid.apple.com/');

        return new AppleIdentityTokenVerifier($client, new ArrayAdapter());
    }

    /** @param array<string, mixed> $override */
    private function token(array $override = [], string $keyId = self::KEY_ID): string
    {
        return JWT::encode($this->claims($override), self::$keys[$keyId]['private'], 'RS256', $keyId);
    }

    /**
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    private function claims(array $override = []): array
    {
        return array_filter([
            'iss' => 'https://appleid.apple.com',
            'aud' => self::CLIENT_ID,
            'exp' => time() + 600,
            'iat' => time(),
            'sub' => '001234.abcdef.1234',
            'email' => 'alice@privaterelay.appleid.com',
            'email_verified' => true,
            'is_private_email' => true,
            ...$override,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /** @return array{private: string, public: string, n: string, e: string} */
    private static function rsaKey(): array
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        self::assertNotFalse($key);
        openssl_pkey_export($key, $privateKey);
        $details = openssl_pkey_get_details($key);
        self::assertIsArray($details);

        return [
            'private' => $privateKey,
            'public' => $details['key'],
            'n' => JWT::urlsafeB64Encode($details['rsa']['n']),
            'e' => JWT::urlsafeB64Encode($details['rsa']['e']),
        ];
    }
}
