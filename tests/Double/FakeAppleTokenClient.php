<?php

declare(strict_types=1);

namespace App\Tests\Double;

use App\Service\OAuth\Apple\AppleTokenClientInterface;
use App\Service\OAuth\Apple\AppleTokenRequestException;
use App\Service\OAuth\Apple\AppleTokens;

/** Apple's token endpoints: a code is good only if a test said so, and every call is recorded. */
final class FakeAppleTokenClient implements AppleTokenClientInterface
{
    /** @var array<string, AppleTokens> */
    private array $tokensByCode = [];

    /** @var list<array{code: string, client_id: string}> */
    public array $exchanges = [];

    /** @var list<array{refresh_token: string, client_id: string}> */
    public array $revocations = [];

    private bool $refusingRevocations = false;

    public function willExchange(string $authorizationCode, string $subject, string $refreshToken): void
    {
        $this->tokensByCode[$authorizationCode] = new AppleTokens($refreshToken, $subject);
    }

    public function refuseRevocations(): void
    {
        $this->refusingRevocations = true;
    }

    public function exchangeAuthorizationCode(string $authorizationCode, string $clientId): AppleTokens
    {
        $this->exchanges[] = ['code' => $authorizationCode, 'client_id' => $clientId];

        return $this->tokensByCode[$authorizationCode] ?? throw new AppleTokenRequestException('Apple refused the request: invalid_grant');
    }

    public function revoke(string $refreshToken, string $clientId): void
    {
        $this->revocations[] = ['refresh_token' => $refreshToken, 'client_id' => $clientId];
        if ($this->refusingRevocations) {
            throw new AppleTokenRequestException('Apple could not be reached');
        }
    }
}
