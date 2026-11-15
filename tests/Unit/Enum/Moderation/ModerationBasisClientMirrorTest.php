<?php declare(strict_types=1);

namespace App\Tests\Unit\Enum\Moderation;

use App\Enum\Moderation\ModerationBasis;
use App\Enum\User\UserEmailType;
use PHPUnit\Framework\TestCase;

/**
 * The suspension dialog offers one basis per ModerationBasis case. A basis the client offers and the
 * server does not know is a 422; one the server knows and the client never offers can never be picked.
 */
class ModerationBasisClientMirrorTest extends TestCase
{
    private const string CONSTANTS_PATH = 'assets/js/constants/moderation.js';

    public function test_the_client_offers_exactly_the_server_bases(): void
    {
        preg_match_all("/\\bvalue: '([a-z_]+)'/", $this->read(), $matches);

        $expected = ModerationBasis::values();
        sort($expected);
        $actual = $matches[1];
        sort($actual);

        $this->assertSame($expected, $actual);
    }

    public function test_the_client_names_every_moderation_email(): void
    {
        preg_match('/MODERATION_EMAIL_LABELS = \{(.*?)\}/s', $this->read(), $block);
        self::assertNotEmpty($block, sprintf('No MODERATION_EMAIL_LABELS found in %s.', self::CONSTANTS_PATH));
        preg_match_all('/^\s*([a-z_]+):/m', $block[1], $matches);

        $expected = array_map(static fn (UserEmailType $type): string => $type->value, UserEmailType::moderation());
        sort($expected);
        $actual = $matches[1];
        sort($actual);

        $this->assertSame($expected, $actual);
    }

    private function read(): string
    {
        // tests/Unit/Enum/Moderation -> project root
        $path = \dirname(__DIR__, 4) . '/' . self::CONSTANTS_PATH;
        self::assertFileExists($path);

        $source = file_get_contents($path);
        self::assertIsString($source);

        return $source;
    }
}
