<?php declare(strict_types=1);

namespace App\Tests\Unit\Entity\Musician;

use App\Entity\Musician\MusicianAnnounce;
use PHPUnit\Framework\TestCase;

/**
 * The announce form and the search filter offer their languages from a JavaScript copy of
 * MusicianAnnounce::LANGUAGES. If the two drift, the form offers a language the API refuses, or the
 * filter misses one an announce can have. The duplication is deliberate, and this is its contract.
 */
class MusicianAnnounceLanguagesMirrorTest extends TestCase
{
    private const string LANGUAGES_PATH = 'assets/js/utils/announceLanguages.js';

    public function test_the_client_language_list_matches_the_entity(): void
    {
        // tests/Unit/Entity/Musician -> project root
        $path = \dirname(__DIR__, 4) . '/' . self::LANGUAGES_PATH;
        self::assertFileExists($path);
        $source = file_get_contents($path);
        self::assertIsString($source);

        preg_match('/^export const ANNOUNCE_LANGUAGES = \[([^\]]*)\]/m', $source, $list);
        self::assertNotEmpty($list, sprintf('No ANNOUNCE_LANGUAGES export found in %s.', self::LANGUAGES_PATH));
        preg_match_all("/'([a-z]+)'/", $list[1], $codes);

        $this->assertSame(MusicianAnnounce::LANGUAGES, $codes[1]);
    }
}
