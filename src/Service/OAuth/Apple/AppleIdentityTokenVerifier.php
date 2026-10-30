<?php

declare(strict_types=1);

namespace App\Service\OAuth\Apple;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Checks an Apple identity token the way Apple documents it (#1269): signature against the published
 * keys, issuer, audience, expiry. Like the Google verifier, a token at fault and Apple out of reach are
 * told apart, so the app can blame the right one.
 */
readonly class AppleIdentityTokenVerifier implements AppleIdentityTokenVerifierInterface
{
    public const string ISSUER = 'https://appleid.apple.com';
    private const string KEYS_PATH = 'auth/keys';
    private const string KEYS_CACHE_KEY = 'apple_signing_keys';
    private const int KEYS_CACHE_TTL = 86400; // a day, like Google's certificates
    private const string ALGORITHM = 'RS256';

    public function __construct(
        #[Target('apple.client')]
        private HttpClientInterface $appleClient,
        #[Target('cache.apple_client')]
        private CacheInterface $cache,
    ) {
    }

    public function verify(string $identityToken, string $audience): array
    {
        // An unset client id would accept a token Apple issued to any other app. Fail closed instead.
        if ($audience === '') {
            throw new AppleIdentityTokenUnverifiableException('The Apple client id is not set');
        }

        $keyId = self::keyIdOf($identityToken);
        $keys = $this->signingKeys();
        if (!isset($keys[$keyId])) {
            // Apple rotates its keys, so a key id missing from the cached set may just be newer than it.
            $keys = $this->signingKeys(refresh: true);
        }
        if (!isset($keys[$keyId])) {
            throw new InvalidAppleIdentityTokenException('Unknown Apple signing key');
        }

        try {
            $claims = json_decode((string) json_encode(JWT::decode($identityToken, $keys[$keyId])), true);
        } catch (\Throwable $exception) {
            throw new InvalidAppleIdentityTokenException('Apple identity token refused', 0, $exception);
        }

        // The library checks `exp` only when there is one: Apple always sets it, so its absence is a forgery.
        if (!is_array($claims)
            || ($claims['iss'] ?? null) !== self::ISSUER
            || ($claims['aud'] ?? null) !== $audience
            || !isset($claims['exp'])
        ) {
            throw new InvalidAppleIdentityTokenException('Apple identity token refused');
        }

        return $claims;
    }

    private static function keyIdOf(string $identityToken): string
    {
        $segments = explode('.', $identityToken);
        if (count($segments) !== 3) {
            throw new InvalidAppleIdentityTokenException('Not a JWT');
        }

        try {
            $header = JWT::jsonDecode(JWT::urlsafeB64Decode($segments[0]));
        } catch (\Throwable $exception) {
            throw new InvalidAppleIdentityTokenException('Unreadable JWT header', 0, $exception);
        }
        $keyId = is_object($header) ? ($header->kid ?? null) : null;
        if (!is_string($keyId) || $keyId === '') {
            throw new InvalidAppleIdentityTokenException('No key id');
        }

        return $keyId;
    }

    /**
     * A refresh recomputes in place rather than deleting first: the key id is the caller's to choose,
     * so a forged one must not be able to empty the cache and leave every sign-in on a failing Apple.
     *
     * @return array<string, Key>
     */
    private function signingKeys(bool $refresh = false): array
    {
        try {
            // The raw set is cached rather than the parsed keys, which hold OpenSSL resources.
            $jwks = $this->cache->get(self::KEYS_CACHE_KEY, function (ItemInterface $item): array {
                $item->expiresAfter(self::KEYS_CACHE_TTL);
                $jwks = $this->appleClient->request('GET', self::KEYS_PATH)->toArray();
                // Parsed before it is cached, so a broken answer is not kept for a day.
                JWK::parseKeySet($jwks, self::ALGORITHM);

                return $jwks;
            }, $refresh ? INF : null);

            return JWK::parseKeySet($jwks, self::ALGORITHM);
        } catch (ExceptionInterface|\UnexpectedValueException|\InvalidArgumentException|\DomainException $exception) {
            throw new AppleIdentityTokenUnverifiableException('Apple\'s signing keys are unavailable', 0, $exception);
        }
    }
}
