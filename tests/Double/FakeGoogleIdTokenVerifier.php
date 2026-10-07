<?php

declare(strict_types=1);

namespace App\Tests\Double;

use App\Service\OAuth\Google\GoogleIdTokenUnverifiableException;
use App\Service\OAuth\Google\GoogleIdTokenVerifierInterface;
use App\Service\OAuth\Google\InvalidGoogleIdTokenException;

/** Google, as far as the suite is concerned: a token is genuine only if a test said so. */
final class FakeGoogleIdTokenVerifier implements GoogleIdTokenVerifierInterface
{
    /** @var array<string, array<string, mixed>> */
    private array $claimsByToken = [];

    private bool $unreachable = false;

    /** @param array<string, mixed> $claims */
    public function accept(string $idToken, array $claims): void
    {
        $this->claimsByToken[$idToken] = $claims;
    }

    public function becomeUnreachable(): void
    {
        $this->unreachable = true;
    }

    public function verify(string $idToken): array
    {
        if ($this->unreachable) {
            throw new GoogleIdTokenUnverifiableException('Google is unreachable');
        }

        return $this->claimsByToken[$idToken] ?? throw new InvalidGoogleIdTokenException('Unknown token');
    }
}
