<?php

declare(strict_types=1);

namespace App\Tests\Double;

use App\Service\OAuth\Apple\AppleTokenClientInterface;
use App\Service\OAuth\Apple\AppleTokenRequestException;
use App\Service\OAuth\Apple\AppleTokens;

/** Apple's token endpoint: a code is good only if a test said so, and every exchange is recorded. */
final class FakeAppleTokenClient implements AppleTokenClientInterface
{
    /** @var array<string, AppleTokens> */
    private array $tokensByCode = [];

    /** @var list<array{code: string, client_id: string}> */
    public array $exchanges = [];

    public function willExchange(string $authorizationCode, string $subject, string $refreshToken): void
    {
        $this->tokensByCode[$authorizationCode] = new AppleTokens($refreshToken, $subject);
    }

    public function exchangeAuthorizationCode(string $authorizationCode, string $clientId): AppleTokens
    {
        $this->exchanges[] = ['code' => $authorizationCode, 'client_id' => $clientId];

        return $this->tokensByCode[$authorizationCode] ?? throw new AppleTokenRequestException('Apple refused the request: invalid_grant');
    }
}
