<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\OAuth\Apple;

use App\Service\OAuth\Apple\AppleSignInAttempt;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AppleSignInAttemptTest extends TestCase
{
    public function test_an_attempt_survives_its_cookie(): void
    {
        $attempt = AppleSignInAttempt::start('/band-space/abc?tab=files');

        $this->assertEquals($attempt, AppleSignInAttempt::fromCookie($attempt->toCookie()));
        $withoutReturnUrl = AppleSignInAttempt::start(null);
        $this->assertEquals($withoutReturnUrl, AppleSignInAttempt::fromCookie($withoutReturnUrl->toCookie()));
    }

    public function test_the_state_and_the_nonce_are_unguessable_and_distinct(): void
    {
        $attempt = AppleSignInAttempt::start(null);

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $attempt->state);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $attempt->nonce);
        $this->assertNotSame($attempt->state, $attempt->nonce);
        $this->assertNotSame($attempt->state, AppleSignInAttempt::start(null)->state);
    }

    /** The cookie is safe to drop in a header: no `+`, `/` or `=` to escape. */
    public function test_the_cookie_value_is_url_safe(): void
    {
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', AppleSignInAttempt::start('/?a=b&c=d')->toCookie());
    }

    /** @return iterable<string, array{string}> */
    public static function unreadableCookies(): iterable
    {
        yield 'empty' => [''];
        yield 'not base64' => ['!!!'];
        yield 'not JSON' => [rtrim(strtr(base64_encode('state'), '+/', '-_'), '=')];
        yield 'a JSON scalar' => [base64_encode('42')];
        yield 'no nonce' => [base64_encode('{"state":"abc","return_url":null}')];
        yield 'an empty state' => [base64_encode('{"state":"","nonce":"abc","return_url":null}')];
        yield 'a return URL that is not a string' => [base64_encode('{"state":"abc","nonce":"def","return_url":["/"]}')];
    }

    #[DataProvider('unreadableCookies')]
    public function test_an_unreadable_cookie_is_no_attempt(string $cookie): void
    {
        $this->assertNull(AppleSignInAttempt::fromCookie($cookie));
    }
}
