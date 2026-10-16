<?php declare(strict_types=1);

namespace App\Tests\Unit\Translation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Formatter\IntlFormatter;
use Symfony\Component\Yaml\Yaml;

/**
 * Best effort checks on the backend catalogues, the PHP twin of assets/js/i18n/fr.test.js. Keys are
 * found by pattern in src/, so a key built at runtime is invisible here: keep every key a literal.
 */
class TranslationCatalogueTest extends TestCase
{
    private const string PROJECT_DIR = __DIR__ . '/../../..';
    private const string MESSAGES = 'messages+intl-icu.fr.yaml';
    private const string VALIDATORS = 'validators.fr.yaml';
    private const string EITHER = 'either catalogue';
    private const string KEY = '[a-z0-9_]+(?:\.[a-z0-9_]+)+';
    private const string QUOTED_KEY = '[\'"](' . self::KEY . ')[\'"]';

    /**
     * Where a key is handed over to be translated, and which catalogue must hold it. A constant ending
     * in MESSAGE may feed an exception or a constraint, so it may be in either file.
     */
    private const array KEY_SITES = [
        self::MESSAGES => [
            '/new [\w\\\\]*Exception\(\s*(?:[^,()\'"]+,\s*)?' . self::QUOTED_KEY . '/',
            '/\'not_found_message\'\s*=>\s*' . self::QUOTED_KEY . '/',
        ],
        self::VALIDATORS => [
            '/\b\w*[mM]essage:\s*' . self::QUOTED_KEY . '/',
            '/\$\w*[mM]essage\s*=\s*' . self::QUOTED_KEY . '/',
            '/buildViolation\(\s*' . self::QUOTED_KEY . '/',
            '/\'invalid_message\'\s*=>\s*' . self::QUOTED_KEY . '/',
        ],
        self::EITHER => ['/const string \w*MESSAGE\w*\s*=\s*' . self::QUOTED_KEY . '/'],
    ];

    /**
     * Literals shaped like a key in a catalogue namespace that are not messages: log event names and a
     * service id. Repositories are not scanned at all, their DQL aliases (`band_space.memberships`)
     * read like keys.
     */
    private const array NOT_KEYS = [
        'auth.consumed_tokens',
        'auth.jwt.rejected',
        'auth.login.success',
        'auth.logout',
        'auth.refresh.failure',
        'auth.refresh.not_found',
        'auth.refresh.success',
        'auth.trail.read_failed',
        'auth.trail.write_failed',
    ];

    /**
     * One key per line, sorted: several people add keys at once, and sorted single lines merge.
     */
    #[DataProvider('catalogues')]
    public function test_a_catalogue_is_flat_and_sorted(string $file): void
    {
        $messages = self::load($file);
        $keys = array_keys($messages);
        $sorted = $keys;
        sort($sorted, SORT_STRING);

        self::assertSame($sorted, $keys, $file . ' is not sorted');
        foreach ($messages as $key => $message) {
            self::assertMatchesRegularExpression('/^' . self::KEY . '$/', (string) $key, $file);
            self::assertIsString($message, $file . ': ' . $key . ' must be a flat dotted key');
        }
    }

    /**
     * ICU reads an apostrophe before `{` as the start of a quoted literal, and French is full of
     * apostrophes: `d'{name}` renders `d{name}`, `d''{name}` is how to write it. A simple message must
     * render as written once `''` reads as `'` and each placeholder holds its value.
     */
    public function test_every_simple_icu_message_renders_as_written(): void
    {
        $formatter = new IntlFormatter();
        foreach (self::load(self::MESSAGES) as $key => $message) {
            if (self::hasComplexArgument($message)) {
                continue;
            }

            preg_match_all('/\{(\w+)\}/', $message, $matches);
            $values = array_map(static fn (string $name): string => '[' . $name . ']', $matches[1]);
            $expected = str_replace($matches[0], $values, str_replace("''", "'", $message));

            self::assertSame($expected, $formatter->formatIntl($message, 'fr', array_combine($matches[1], $values)), $key);
        }
    }

    /**
     * A plural or a select cannot read back as written, but a syntax error in it still throws.
     */
    public function test_every_plural_or_select_icu_message_formats(): void
    {
        $formatter = new IntlFormatter();
        $empty = [];
        foreach (self::load(self::MESSAGES) as $key => $message) {
            if (!self::hasComplexArgument($message)) {
                continue;
            }

            preg_match_all('/\{\s*(\w+)\s*[,}]/', $message, $matches);
            foreach ([1, 1000] as $number) {
                if ($formatter->formatIntl($message, 'fr', array_fill_keys($matches[1], $number)) === '') {
                    $empty[] = $key;
                }
            }
        }

        self::assertSame([], $empty);
    }

    #[DataProvider('catalogues')]
    public function test_every_key_is_used_in_src(string $file): void
    {
        preg_match_all('/' . self::QUOTED_KEY . '/', self::sources(), $matches);
        $literals = array_flip($matches[1]);

        $unused = array_keys(array_diff_key(self::load($file), $literals));

        self::assertSame([], $unused, $file . ' holds keys that nothing in src/ uses');
    }

    public function test_every_key_given_to_an_exception_or_a_constraint_exists(): void
    {
        $catalogues = [
            self::MESSAGES => self::load(self::MESSAGES),
            self::VALIDATORS => self::load(self::VALIDATORS),
        ];
        $catalogues[self::EITHER] = $catalogues[self::MESSAGES] + $catalogues[self::VALIDATORS];

        $missing = [];
        foreach (self::KEY_SITES as $catalogue => $patterns) {
            foreach ($patterns as $pattern) {
                preg_match_all($pattern, self::sources(), $matches);
                foreach ($matches[1] as $key) {
                    if (!isset($catalogues[$catalogue][$key])) {
                        $missing[] = $catalogue . ': ' . $key;
                    }
                }
            }
        }

        self::assertSame([], array_values(array_unique($missing)));
    }

    /**
     * A catch-all for keys handed over some other way (an argument, a match arm, an enum): any literal
     * in a namespace the catalogues use must be a key one of them holds.
     */
    public function test_every_literal_in_a_catalogue_namespace_is_a_key(): void
    {
        $keys = self::load(self::MESSAGES) + self::load(self::VALIDATORS);
        $namespaces = array_unique(array_map(static fn (string $key): string => explode('.', $key)[0], array_keys($keys)));

        preg_match_all('/[\'"]((?:' . implode('|', $namespaces) . ')(?:\.[a-z0-9_]+)+)[\'"]/', self::sources(excludeRepositories: true), $matches);
        $unknown = array_diff(array_unique($matches[1]), array_keys($keys), self::NOT_KEYS);

        self::assertSame([], array_values($unknown));
    }

    /**
     * A message with a placeholder only renders through an exception that carries its parameters: any
     * other class would show `{name}` to the user.
     */
    public function test_a_key_with_a_placeholder_is_thrown_with_its_parameters(): void
    {
        $messages = self::load(self::MESSAGES);
        preg_match_all('/class (\w+)[^{]*\bimplements\b[^{]*\bTranslatableInterface\b/', self::sources(), $translatable);

        preg_match_all('/new ([\w\\\\]*Exception)\(\s*(?:[^,()\'"]+,\s*)?' . self::QUOTED_KEY . '/', self::sources(), $throws, PREG_SET_ORDER);
        $wrong = [];
        foreach ($throws as [, $class, $key]) {
            $shortName = substr((string) strrchr('\\' . $class, '\\'), 1);
            if (str_contains($messages[$key] ?? '', '{') && !in_array($shortName, $translatable[1], true)) {
                $wrong[] = $class . ': ' . $key;
            }
        }

        self::assertSame([], array_values(array_unique($wrong)));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function catalogues(): iterable
    {
        yield self::MESSAGES => [self::MESSAGES];
        yield self::VALIDATORS => [self::VALIDATORS];
    }

    private static function hasComplexArgument(string $message): bool
    {
        return preg_match('/\{\s*\w+\s*,\s*(?:plural|select|selectordinal|number)\b/', $message) === 1;
    }

    /**
     * @return array<string, mixed>
     */
    private static function load(string $file): array
    {
        return Yaml::parseFile(self::PROJECT_DIR . '/translations/' . $file) ?? [];
    }

    private static function sources(bool $excludeRepositories = false): string
    {
        $sources = '';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::PROJECT_DIR . '/src'));
        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            if ($excludeRepositories && str_contains($file->getPathname(), '/src/Repository/')) {
                continue;
            }
            $sources .= file_get_contents($file->getPathname()) . "\n";
        }

        return $sources;
    }
}
