<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\OAuth\Apple;

use App\Service\OAuth\Apple\AppleUserDataMapper;
use App\Service\OAuth\Apple\InvalidAppleIdentityTokenException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AppleUserDataMapperTest extends TestCase
{
    public function test_maps_the_claims_and_the_name_sent_on_the_first_sign_in(): void
    {
        $data = AppleUserDataMapper::fromClaims(['sub' => '001234.abc', 'email' => 'x7k2@privaterelay.appleid.com', 'email_verified' => 'true'], 'Élodie', 'Dupré');

        $this->assertSame('001234.abc', $data->id);
        $this->assertSame('x7k2@privaterelay.appleid.com', $data->email);
        $this->assertSame('Élodie Dupré', $data->username);
        $this->assertSame('Élodie Dupré', $data->displayName);
        $this->assertNull($data->pictureUrl);
        $this->assertTrue($data->emailVerified);
    }

    public function test_an_email_is_untrusted_unless_apple_says_it_is_verified(): void
    {
        $this->assertFalse(AppleUserDataMapper::fromClaims(['sub' => '1', 'email' => 'a@example.com'], null, null)->emailVerified);
        $this->assertFalse(AppleUserDataMapper::fromClaims(['sub' => '1', 'email' => 'a@example.com', 'email_verified' => 'false'], null, null)->emailVerified);
        $this->assertTrue(AppleUserDataMapper::fromClaims(['sub' => '1', 'email' => 'a@example.com', 'email_verified' => true], null, null)->emailVerified);
    }

    /** Never the whole address: a relay address would make a username out of `privaterelayappleidcom`. */
    public function test_without_a_name_the_local_part_of_the_email_stands_in(): void
    {
        $data = AppleUserDataMapper::fromClaims(['sub' => '1', 'email' => 'x7k2@privaterelay.appleid.com'], null, null);

        $this->assertSame('x7k2', $data->username);
        $this->assertNull($data->displayName);
    }

    public function test_a_given_name_alone_is_a_name(): void
    {
        $this->assertSame('Alice', AppleUserDataMapper::fromClaims(['sub' => '1', 'email' => 'a@example.com'], '  Alice ', null)->displayName);
    }

    /** @return iterable<string, array{string}> */
    public static function namesTheProfileRefuses(): iterable
    {
        yield 'an emoji' => ['Alice 🎸'];
        yield 'a direction override' => ["Alice\u{202E}"];
        yield 'a leading at' => ['@alice'];
        yield 'too long' => [str_repeat('a', 101)];
    }

    /** The account is still created, just without a name it would refuse on the profile page. */
    #[DataProvider('namesTheProfileRefuses')]
    public function test_a_name_the_profile_refuses_is_dropped(string $givenName): void
    {
        $data = AppleUserDataMapper::fromClaims(['sub' => '1', 'email' => 'alice@example.com'], $givenName, null);

        $this->assertNull($data->displayName);
        $this->assertSame('alice', $data->username);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function incompleteIdentities(): iterable
    {
        yield 'no id' => [['email' => 'a@example.com']];
        yield 'an id that is not a string' => [['sub' => 42, 'email' => 'a@example.com']];
        yield 'no email' => [['sub' => '1']];
    }

    /** @param array<string, mixed> $claims */
    #[DataProvider('incompleteIdentities')]
    public function test_an_incomplete_identity_is_refused(array $claims): void
    {
        $this->expectException(InvalidAppleIdentityTokenException::class);

        AppleUserDataMapper::fromClaims($claims, null, null);
    }
}
