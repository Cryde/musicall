<?php

declare(strict_types=1);

namespace App\Tests\Double;

use App\Service\OAuth\Apple\AppleIdentityTokenUnverifiableException;
use App\Service\OAuth\Apple\AppleIdentityTokenVerifierInterface;
use App\Service\OAuth\Apple\InvalidAppleIdentityTokenException;

/**
 * Apple, as far as the suite is concerned: a token is genuine only if a test said so, and only for the
 * audience it was issued to, so the client id the endpoint checks against is part of what is tested.
 */
final class FakeAppleIdentityTokenVerifier implements AppleIdentityTokenVerifierInterface
{
    /** @var array<string, array{audience: string, claims: array<string, mixed>}> */
    private array $tokens = [];

    private bool $unreachable = false;

    /** @param array<string, mixed> $claims */
    public function accept(string $identityToken, array $claims, string $audience = 'com.musicall.app'): void
    {
        $this->tokens[$identityToken] = ['audience' => $audience, 'claims' => $claims];
    }

    public function becomeUnreachable(): void
    {
        $this->unreachable = true;
    }

    public function verify(string $identityToken, string $audience): array
    {
        if ($this->unreachable) {
            throw new AppleIdentityTokenUnverifiableException('Apple is unreachable');
        }
        $token = $this->tokens[$identityToken] ?? null;
        if ($token === null || $token['audience'] !== $audience) {
            throw new InvalidAppleIdentityTokenException('Unknown token');
        }

        return $token['claims'];
    }
}
