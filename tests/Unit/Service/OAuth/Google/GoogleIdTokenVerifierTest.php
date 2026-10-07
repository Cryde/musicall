<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\OAuth\Google;

use App\Service\OAuth\Google\GoogleIdTokenUnverifiableException;
use App\Service\OAuth\Google\GoogleIdTokenVerifier;
use App\Service\OAuth\Google\InvalidGoogleIdTokenException;
use Firebase\JWT\JWT;
use Google\Client;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The real Google library, against tokens signed here with a throwaway key whose certificate Google's
 * endpoint is mocked to publish (#1133). What Google itself would refuse is refused, and each failure
 * lands on the right side: the token's fault (401) or Google's (503).
 */
class GoogleIdTokenVerifierTest extends TestCase
{
    private const string CLIENT_ID = 'musicall-web.apps.googleusercontent.com';
    private const string KEY_ID = 'test-key';

    private static string $privateKey;

    /** @var array{n: string, e: string} */
    private static array $publicKey;

    public static function setUpBeforeClass(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        self::assertNotFalse($key);
        openssl_pkey_export($key, $privateKey);
        self::$privateKey = $privateKey;
        $details = openssl_pkey_get_details($key);
        self::assertIsArray($details);
        self::$publicKey = ['n' => self::base64Url($details['rsa']['n']), 'e' => self::base64Url($details['rsa']['e'])];
    }

    public function test_a_genuine_token_gives_its_claims(): void
    {
        $claims = $this->verifier()->verify($this->token());

        $this->assertSame('42', $claims['sub']);
        $this->assertSame('alice@example.com', $claims['email']);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function refusedClaims(): iterable
    {
        yield 'another app\'s client' => [['aud' => 'someone-else.apps.googleusercontent.com']];
        yield 'no audience at all' => [['aud' => null]];
        yield 'not issued by Google' => [['iss' => 'https://evil.example.com']];
        yield 'expired' => [['exp' => time() - 3600, 'iat' => time() - 7200]];
    }

    /** @param array<string, mixed> $override */
    #[DataProvider('refusedClaims')]
    public function test_a_token_google_would_refuse_is_invalid(array $override): void
    {
        $this->expectException(InvalidGoogleIdTokenException::class);

        $this->verifier()->verify($this->token($override));
    }

    public function test_a_token_signed_with_another_key_is_invalid(): void
    {
        $forger = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        self::assertNotFalse($forger);
        openssl_pkey_export($forger, $forgedKey);

        $this->expectException(InvalidGoogleIdTokenException::class);

        $this->verifier()->verify(JWT::encode($this->claims(), $forgedKey, 'RS256', self::KEY_ID));
    }

    public function test_a_key_google_does_not_publish_is_invalid(): void
    {
        $this->expectException(InvalidGoogleIdTokenException::class);

        $this->verifier()->verify(JWT::encode($this->claims(), self::$privateKey, 'RS256', 'unknown-key'));
    }

    public function test_something_that_is_not_a_jwt_is_invalid(): void
    {
        $this->expectException(InvalidGoogleIdTokenException::class);

        $this->verifier()->verify('not-a-jwt');
    }

    public function test_google_answering_with_an_error_is_unverifiable(): void
    {
        $this->expectException(GoogleIdTokenUnverifiableException::class);

        $this->verifier(new Response(503))->verify($this->token());
    }

    public function test_google_out_of_reach_is_unverifiable(): void
    {
        $this->expectException(GoogleIdTokenUnverifiableException::class);

        $this->verifier(new ConnectException('Connection refused', new Request('GET', 'https://www.googleapis.com/oauth2/v3/certs')))
            ->verify($this->token());
    }

    /** An unset client id would turn the library's audience check off, so nothing is accepted. */
    public function test_without_a_client_id_nothing_is_accepted(): void
    {
        $this->expectException(GoogleIdTokenUnverifiableException::class);

        $this->verifier(clientId: '')->verify($this->token());
    }

    private function verifier(Response|\Throwable|null $certificates = null, string $clientId = self::CLIENT_ID): GoogleIdTokenVerifier
    {
        $client = new Client();
        $client->setClientId($clientId);
        $client->setHttpClient(new GuzzleClient(['handler' => HandlerStack::create(new MockHandler([
            $certificates ?? new Response(200, ['Content-Type' => 'application/json'], (string) json_encode(['keys' => [[
                'kty' => 'RSA',
                'alg' => 'RS256',
                'use' => 'sig',
                'kid' => self::KEY_ID,
                'n' => self::$publicKey['n'],
                'e' => self::$publicKey['e'],
            ]]])),
        ]))]));

        return new GoogleIdTokenVerifier($client);
    }

    /** @param array<string, mixed> $override */
    private function token(array $override = []): string
    {
        return JWT::encode(array_filter(array_merge($this->claims(), $override), static fn ($value): bool => $value !== null), self::$privateKey, 'RS256', self::KEY_ID);
    }

    /** @return array<string, mixed> */
    private function claims(): array
    {
        return [
            'iss' => 'https://accounts.google.com',
            'aud' => self::CLIENT_ID,
            'sub' => '42',
            'email' => 'alice@example.com',
            'email_verified' => true,
            'iat' => time(),
            'exp' => time() + 3600,
        ];
    }

    private static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
