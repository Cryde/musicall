<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\OAuth\Google;

use App\Service\OAuth\Google\GoogleUserDataMapper;
use App\Service\OAuth\Google\InvalidGoogleIdTokenException;
use PHPUnit\Framework\TestCase;

class GoogleUserDataMapperTest extends TestCase
{
    public function test_maps_the_claims_both_sign_in_paths_receive(): void
    {
        $data = GoogleUserDataMapper::fromClaims([
            'sub' => '42',
            'email' => 'alice@example.com',
            'email_verified' => true,
            'name' => 'Alice Martin',
            'picture' => 'https://lh3.googleusercontent.com/a/abc=s96-c',
        ]);

        $this->assertSame('42', $data->id);
        $this->assertSame('alice@example.com', $data->email);
        $this->assertSame('Alice Martin', $data->username);
        $this->assertSame('https://lh3.googleusercontent.com/a/abc=s500-c', $data->pictureUrl);
        $this->assertTrue($data->emailVerified);
    }

    public function test_an_email_is_untrusted_unless_google_says_it_is_verified(): void
    {
        $this->assertFalse(GoogleUserDataMapper::fromClaims(['sub' => '42', 'email' => 'a@example.com'])->emailVerified);
        $this->assertFalse(GoogleUserDataMapper::fromClaims(['sub' => '42', 'email' => 'a@example.com', 'email_verified' => 'false'])->emailVerified);
        $this->assertTrue(GoogleUserDataMapper::fromClaims(['sub' => '42', 'email' => 'a@example.com', 'email_verified' => 'true'])->emailVerified);
    }

    public function test_without_a_name_the_email_stands_in(): void
    {
        $data = GoogleUserDataMapper::fromClaims(['sub' => '42', 'email' => 'alice@example.com']);

        $this->assertSame('alice@example.com', $data->username);
        $this->assertNull($data->pictureUrl);
    }

    public function test_an_identity_without_an_email_is_refused(): void
    {
        $this->expectException(InvalidGoogleIdTokenException::class);

        GoogleUserDataMapper::fromClaims(['sub' => '42']);
    }
}
