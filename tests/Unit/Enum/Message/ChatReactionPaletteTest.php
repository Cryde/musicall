<?php declare(strict_types=1);

namespace App\Tests\Unit\Enum\Message;

use App\Enum\Message\MessageReactionEmoji;
use PHPUnit\Framework\TestCase;

/**
 * The reaction palette is defined twice: once as a PHP enum, because it is the allow list the API
 * validates against and the source of the character it hands out, and once in
 * assets/js/constants/chatReactions.js, because the picker has to offer every reaction including the
 * ones nobody has used yet, which the aggregate by definition never mentions.
 *
 * Serving the palette from an endpoint would buy nothing: the enum is PHP, so changing it is a
 * deploy, and the frontend bundle rebuilds in the same deploy. So the two definitions stay, and this
 * is the contract between them. Drift is the failure mode that matters, and drift fails here.
 *
 * The French label is deliberately not part of the contract: nothing in PHP renders it, and a name
 * only the browser reads belongs with the browser.
 */
class ChatReactionPaletteTest extends TestCase
{
    private const string PALETTE_PATH = 'assets/js/constants/chatReactions.js';

    public function test_the_php_enum_and_the_javascript_palette_hold_the_same_reactions(): void
    {
        $this->assertSame(
            array_map(
                static fn (MessageReactionEmoji $reaction): array => [
                    'key' => $reaction->value,
                    'emoji' => $reaction->character(),
                ],
                MessageReactionEmoji::cases(),
            ),
            $this->paletteFromJavaScript(),
            sprintf(
                'The reaction palette has drifted. Update %s and %s together, in the same order.',
                self::PALETTE_PATH,
                MessageReactionEmoji::class,
            ),
        );
    }

    /**
     * The slug is what goes in the database and in the remove endpoint's URL, so it has to stay
     * something a path segment can carry without escaping.
     */
    public function test_every_slug_is_lowercase_ascii(): void
    {
        foreach (MessageReactionEmoji::cases() as $reaction) {
            $this->assertMatchesRegularExpression('/^[a-z][a-z_]*\z/', $reaction->value);
        }
    }

    /**
     * @return list<array{key: string, emoji: string}>
     */
    private function paletteFromJavaScript(): array
    {
        // tests/Unit/Enum/Message -> project root
        $path = \dirname(__DIR__, 4) . '/' . self::PALETTE_PATH;
        self::assertFileExists($path);

        $source = file_get_contents($path);
        self::assertIsString($source);

        preg_match_all(
            "/\{\s*key:\s*'([^']+)',\s*emoji:\s*'([^']+)',\s*label:\s*'[^']+'\s*}/u",
            $source,
            $matches,
            PREG_SET_ORDER,
        );

        // Guards against the regex silently matching nothing, which would turn the comparison above
        // into "the enum differs from an empty list" instead of a drift report.
        self::assertNotEmpty($matches, sprintf('No reaction entries were parsed out of %s.', self::PALETTE_PATH));

        return array_map(
            static fn (array $match): array => ['key' => $match[1], 'emoji' => $match[2]],
            $matches,
        );
    }
}
