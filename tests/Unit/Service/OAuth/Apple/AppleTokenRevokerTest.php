<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\OAuth\Apple;

use App\Entity\SocialAccount;
use App\Entity\User;
use App\Service\OAuth\Apple\AppleRefreshToken;
use App\Service\OAuth\Apple\AppleTokenClientInterface;
use App\Service\OAuth\Apple\AppleTokenRevoker;
use App\Service\OAuth\Apple\AppleTokens;
use App\Tests\Double\FakeAppleTokenClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Symfony\Component\ErrorHandler\BufferingLogger;

/** The deletion is committed before this runs (#1270): nothing it does may throw, and every token gets its try. */
class AppleTokenRevokerTest extends TestCase
{
    private const string USER_ID = '0199a3c4-0000-7000-8000-000000000001';

    public function test_only_apple_accounts_holding_a_token_have_one_to_revoke(): void
    {
        $user = new User();
        $this->socialAccount($user, SocialAccount::PROVIDER_APPLE, 'r.app-token', 'com.musicall.app');
        $this->socialAccount($user, SocialAccount::PROVIDER_APPLE, null, null);
        $this->socialAccount($user, SocialAccount::PROVIDER_GOOGLE, null, null);

        $refreshTokens = (new AppleTokenRevoker(new FakeAppleTokenClient(), new BufferingLogger()))->refreshTokensOf($user);

        $this->assertEquals([new AppleRefreshToken('r.app-token', 'com.musicall.app')], $refreshTokens);
    }

    public function test_every_token_is_revoked_under_its_client_id(): void
    {
        $client = new FakeAppleTokenClient();
        $logger = new BufferingLogger();

        (new AppleTokenRevoker($client, $logger))->revoke([
            new AppleRefreshToken('r.app-token', 'com.musicall.app'),
            new AppleRefreshToken('r.web-token', 'com.musicall.web'),
        ], self::USER_ID);

        $this->assertSame([
            ['refresh_token' => 'r.app-token', 'client_id' => 'com.musicall.app'],
            ['refresh_token' => 'r.web-token', 'client_id' => 'com.musicall.web'],
        ], $client->revocations);
        $this->assertSame([], $logger->cleanLogs());
    }

    /** A failure is logged with who it was for, since the account that held the token is gone, and the next token still gets its try. */
    public function test_a_failed_revocation_is_logged_and_the_next_one_still_runs(): void
    {
        $client = new FakeAppleTokenClient();
        $client->refuseRevocations();
        $logger = new BufferingLogger();

        (new AppleTokenRevoker($client, $logger))->revoke([
            new AppleRefreshToken('r.app-token', 'com.musicall.app'),
            new AppleRefreshToken('r.web-token', 'com.musicall.web'),
        ], self::USER_ID);

        $this->assertCount(2, $client->revocations);
        $logs = $logger->cleanLogs();
        $this->assertSame(
            [
                [LogLevel::ERROR, 'Could not revoke an Apple refresh token of a deleted account', self::USER_ID, 'com.musicall.app'],
                [LogLevel::ERROR, 'Could not revoke an Apple refresh token of a deleted account', self::USER_ID, 'com.musicall.web'],
            ],
            array_map(static fn (array $log): array => [$log[0], $log[1], $log[2]['user_id'], $log[2]['client_id']], $logs),
        );
        $this->assertSame('Apple could not be reached', $logs[0][2]['exception']->getMessage());
    }

    /** Not only Apple's own failures: whatever goes wrong, the committed deletion does not turn into a 500. */
    public function test_an_unexpected_error_is_logged_too(): void
    {
        $failure = new \TypeError('Something broke');
        $client = new class($failure) implements AppleTokenClientInterface {
            public function __construct(private readonly \Throwable $failure)
            {
            }

            public function exchangeAuthorizationCode(string $authorizationCode, string $clientId): AppleTokens
            {
                throw new \LogicException('Not used');
            }

            public function revoke(string $refreshToken, string $clientId): void
            {
                throw $this->failure;
            }
        };
        $logger = new BufferingLogger();

        (new AppleTokenRevoker($client, $logger))->revoke([new AppleRefreshToken('r.app-token', 'com.musicall.app')], self::USER_ID);

        $this->assertSame([[
            LogLevel::ERROR,
            'Could not revoke an Apple refresh token of a deleted account',
            ['exception' => $failure, 'user_id' => self::USER_ID, 'client_id' => 'com.musicall.app'],
        ]], $logger->cleanLogs());
    }

    private function socialAccount(User $user, string $provider, ?string $refreshToken, ?string $clientId): void
    {
        $socialAccount = new SocialAccount();
        $socialAccount->provider = $provider;
        $socialAccount->providerId = $provider . '-id';
        $socialAccount->refreshToken = $refreshToken;
        $socialAccount->refreshTokenClientId = $clientId;
        $user->addSocialAccount($socialAccount);
    }
}
