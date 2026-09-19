<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\Observability;

use App\Security\Observability\AuthFailureReason;
use Gesdinet\JWTRefreshTokenBundle\Security\Exception\TokenNotFoundException;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\ExpiredTokenException;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\InvalidTokenException;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\MissingTokenException;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\UserNotFoundException;
use PHPUnit\Framework\TestCase;

class AuthFailureReasonTest extends TestCase
{
    /**
     * The three Lexik reasons are the whole point of the field: they are three different diagnoses
     * and a query that cannot tell them apart is a query that only knows the 401 count.
     */
    public function test_the_fixed_reasons_are_kept_verbatim(): void
    {
        $reason = new AuthFailureReason();

        $this->assertSame('Expired JWT Token', $reason->fromException(new ExpiredTokenException()));
        $this->assertSame('Invalid JWT Token', $reason->fromException(new InvalidTokenException()));
        $this->assertSame('JWT Token not found', $reason->fromException(new MissingTokenException()));
        $this->assertSame('JWT Refresh Token Not Found', $reason->fromException(new TokenNotFoundException()));
    }

    /**
     * The reason this class exists rather than a straight getMessageKey() call. Lexik builds this
     * one out of the identity it failed to load, so passing it through would put usernames in
     * Sentry. This is the exact shape produced by a username change, which is issue #1025.
     */
    public function test_an_identity_bearing_message_is_never_logged(): void
    {
        $exception = new UserNotFoundException('username', 'the-actual-username');

        $result = new AuthFailureReason()->fromException($exception);

        $this->assertSame('UserNotFoundException', $result);
        $this->assertStringNotContainsString('the-actual-username', (string) $result);
    }

    public function test_no_exception_yields_no_reason(): void
    {
        $this->assertNull(new AuthFailureReason()->fromException(null));
    }
}
