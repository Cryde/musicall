<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\User;

use App\Service\User\RegistrationCampaign;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RegistrationCampaignTest extends TestCase
{
    public function test_it_keeps_what_a_browser_sends(): void
    {
        $campaign = RegistrationCampaign::fromFields([
            'utm_source' => 'google',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'pmax-musiciens',
            'referrer_host' => 'www.google.com',
        ]);

        $this->assertSame([
            'utm_source' => 'google',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'pmax-musiciens',
            'referrer_host' => 'www.google.com',
        ], $campaign->toFields());
        $this->assertFalse($campaign->isEmpty());
    }

    public function test_nothing_sent_is_an_empty_campaign(): void
    {
        $campaign = RegistrationCampaign::fromFields([]);

        $this->assertSame(['utm_source' => null, 'utm_medium' => null, 'utm_campaign' => null, 'referrer_host' => null], $campaign->toFields());
        $this->assertTrue($campaign->isEmpty());
    }

    /** A value read back from where it was stashed goes through the same rules. */
    public function test_its_fields_read_back_as_the_same_campaign(): void
    {
        $campaign = RegistrationCampaign::fromFields(['utm_campaign' => 'Black Friday 2026', 'referrer_host' => 'm.facebook.com']);

        $this->assertSame($campaign->toFields(), RegistrationCampaign::fromFields($campaign->toFields())->toFields());
    }

    #[DataProvider('utmValues')]
    public function test_a_utm_value_is_trimmed_and_lowercased_or_dropped(mixed $value, ?string $expected): void
    {
        $fields = RegistrationCampaign::fromFields(['utm_source' => $value, 'utm_medium' => $value, 'utm_campaign' => $value])->toFields();

        $this->assertSame([$expected, $expected, $expected], [$fields['utm_source'], $fields['utm_medium'], $fields['utm_campaign']]);
    }

    /** @return iterable<string, array{mixed, ?string}> */
    public static function utmValues(): iterable
    {
        yield 'a plain name' => ['newsletter', 'newsletter'];
        yield 'spaces around' => ["  pmax-musiciens \t", 'pmax-musiciens'];
        yield 'lowercased, so Google and google are one source' => ['Google', 'google'];
        yield 'words, accents and separators' => ["L'Été des Musiciens | FR_2026/v2.1+b:x", "l'été des musiciens | fr_2026/v2.1+b:x"];
        yield 'another script' => ['音楽', '音楽'];
        yield 'exactly 100 characters' => [str_repeat('É', 100), str_repeat('é', 100)];
        yield 'over 100 characters' => [str_repeat('a', 101), null];
        yield 'empty' => ['', null];
        yield 'only spaces' => ['   ', null];
        yield 'a control character inside' => ["pmax\u{0007}musiciens", null];
        yield 'a newline inside' => ["pmax\nmusiciens", null];
        yield 'a null byte inside' => ["pm\0ax", null];
        yield 'markup' => ['<script>alert(1)</script>', null];
        yield 'parentheses, kept for « (direct) »' => ['(direct)', null];
        yield 'invalid UTF-8' => ["pmax\xC3\x28", null];
        yield 'a number' => [42, null];
        yield 'a list' => [['google'], null];
        yield 'null' => [null, null];
    }

    #[DataProvider('hosts')]
    public function test_a_referrer_host_is_lowercased_or_dropped(mixed $value, ?string $expected): void
    {
        $this->assertSame($expected, RegistrationCampaign::fromFields(['referrer_host' => $value])->referrerHost);
    }

    /** @return iterable<string, array{mixed, ?string}> */
    public static function hosts(): iterable
    {
        yield 'a host' => ['www.google.com', 'www.google.com'];
        yield 'uppercase' => ['WWW.Google.COM', 'www.google.com'];
        yield 'spaces around' => [' l.instagram.com ', 'l.instagram.com'];
        yield 'an android app' => ['com.google.android.gm', 'com.google.android.gm'];
        yield 'punycode' => ['xn--musique-bya.fr', 'xn--musique-bya.fr'];
        yield 'Google sign-in, back from a failed attempt' => ['accounts.google.com', null];
        yield 'Apple sign-in, back from a failed attempt' => ['AppleID.Apple.com', null];
        yield 'another Google site still counts' => ['mail.google.com', 'mail.google.com'];
        yield 'a single label' => ['localhost', 'localhost'];
        yield 'exactly 253 characters' => [self::hostOfLength(253), self::hostOfLength(253)];
        yield 'over 253 characters' => [self::hostOfLength(254), null];
        yield 'a URL, not a host' => ['https://www.google.com/search?q=musicall', null];
        yield 'a path' => ['google.com/search', null];
        yield 'a port' => ['google.com:443', null];
        yield 'an empty label' => ['google..com', null];
        yield 'a leading dot' => ['.google.com', null];
        yield 'a label ending with a hyphen' => ['google-.com', null];
        yield 'not punycode' => ['musiqué.fr', null];
        yield 'a trailing newline, trimmed' => ["google.com\n", 'google.com'];
        yield 'a control character inside' => ["goo\u{0007}gle.com", null];
        yield 'empty' => ['', null];
        yield 'a number' => [42, null];
        yield 'a list' => [['google.com'], null];
    }

    private static function hostOfLength(int $length): string
    {
        // Labels of 49 letters joined by dots, cut to the length asked for and ending on a letter.
        return substr(implode('.', array_fill(0, 6, str_repeat('a', 49))), 0, $length - 1) . 'b';
    }
}
