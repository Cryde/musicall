<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\User;

use App\Service\User\DisplayNameRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DisplayNameRulesTest extends TestCase
{
    public function test_normalize_collapses_whitespace_and_trims(): void
    {
        $this->assertSame('Jean Dupont', DisplayNameRules::normalize("  Jean \t  Dupont\n "));
    }

    public function test_normalize_turns_blank_into_no_name(): void
    {
        $this->assertNull(DisplayNameRules::normalize('   '));
        $this->assertNull(DisplayNameRules::normalize(null));
    }

    public function test_normalize_composes_accents(): void
    {
        // « e » followed by a combining acute becomes the single « é ».
        $this->assertSame("Andr\u{00E9}", DisplayNameRules::normalize("Andre\u{0301}"));
    }

    public function test_invalid_utf8_is_kept_and_then_refused(): void
    {
        $name = DisplayNameRules::normalize("Alex\xC3\x28");

        $this->assertSame("Alex\xC3\x28", $name);
        $this->assertSame(DisplayNameRules::FORBIDDEN_CHARACTER, DisplayNameRules::violation((string) $name));
    }

    /** @return iterable<string, array{string}> */
    public static function acceptedNames(): iterable
    {
        yield 'accents' => ['Mötley Crüe'];
        yield 'slash' => ['AC/DC'];
        yield 'apostrophe' => ["Guns N' Roses"];
        yield 'another script' => ['李小龙'];
        yield 'digits' => ['Blink 182'];
        yield 'two marks' => ["Vi\u{0323}\u{0302}t"];
        yield 'persian with a zero width non joiner' => ["می\u{200C}خواهم"];
    }

    #[DataProvider('acceptedNames')]
    public function test_a_real_name_is_accepted(string $name): void
    {
        $this->assertNull(DisplayNameRules::violation($name));
    }

    /** @return iterable<string, array{string, string}> */
    public static function refusedNames(): iterable
    {
        yield 'zero width space' => ["Ale\u{200B}x", DisplayNameRules::FORBIDDEN_CHARACTER];
        yield 'right to left override' => ["Alex\u{202E}nimda", DisplayNameRules::FORBIDDEN_CHARACTER];
        yield 'control character' => ["Alex\u{0007}", DisplayNameRules::FORBIDDEN_CHARACTER];
        yield 'hangul filler, a blank looking letter' => ["\u{3164}", DisplayNameRules::FORBIDDEN_CHARACTER];
        yield 'byte order mark' => ["\u{FEFF}Alex", DisplayNameRules::FORBIDDEN_CHARACTER];
        yield 'stacked marks' => ["Z\u{0301}\u{0302}\u{0303}", DisplayNameRules::STACKED_MARKS];
        yield 'punctuation only' => ['!!!', DisplayNameRules::NO_LETTER_OR_DIGIT];
        yield 'emoji next to letters' => ['Alex 🎸', DisplayNameRules::EMOJI];
        yield 'emoji only' => ['🎸🎸', DisplayNameRules::EMOJI];
        yield 'emoji family sequence' => ["Famille \u{1F468}\u{200D}\u{1F469}\u{200D}\u{1F467}", DisplayNameRules::EMOJI];
        yield 'flag' => ["Alex \u{1F1EB}\u{1F1F7}", DisplayNameRules::EMOJI];
        yield 'keycap' => ["Alex 1\u{FE0F}\u{20E3}", DisplayNameRules::EMOJI];
        yield 'music note symbol' => ['♪ Alex', DisplayNameRules::EMOJI];
        yield 'looks like a handle' => ['@user_admin', DisplayNameRules::LEADING_AT];
        yield 'deleted account label' => ['utilisateur supprimé', DisplayNameRules::RESERVED];
        yield 'the site' => ['MUSICALL', DisplayNameRules::RESERVED];
    }

    #[DataProvider('refusedNames')]
    public function test_a_harmful_name_is_refused(string $name, string $rule): void
    {
        $this->assertSame($rule, DisplayNameRules::violation($name));
    }
}
