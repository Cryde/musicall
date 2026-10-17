<?php declare(strict_types=1);

namespace App\Tests\Unit\Translation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Formatter\IntlFormatter;
use Symfony\Component\Yaml\Yaml;

/**
 * Best effort checks on the backend catalogues, the PHP twin of assets/js/i18n/fr.test.js. Keys are
 * found by pattern in src/, comments left out, so a key built at runtime is invisible here: keep every
 * key a literal.
 */
class TranslationCatalogueTest extends TestCase
{
    private const string PROJECT_DIR = __DIR__ . '/../../..';
    private const string MESSAGES = 'messages+intl-icu.fr.yaml';
    private const string VALIDATORS = 'validators.fr.yaml';
    private const string REFERENCE = 'reference+intl-icu.fr.yaml';
    private const string KEY = '[a-z0-9_]+(?:\.[a-z0-9_]+)+';

    /**
     * What precedes a key handed to an exception: its message argument, wherever it sits among the
     * arguments (`new UnauthorizedHttpException('Bearer', ...)`), or a `not_found_message`.
     */
    private const string EXCEPTION_ARGUMENT = '/(?:(?:new [\w\\\\]*Exception|parent::__construct)\(\s*(?:[^;]{0,80}?,\s*)?|not_found_message\'\]?\s*(?:=>|\?\?)\s*)$/';

    /**
     * What precedes a key handed to the validator: a `*message*` argument or property, a violation,
     * or an `invalid_message`.
     */
    private const string CONSTRAINT_ARGUMENT = '/(?:\b\w*[mM]essage\w*:\s*|\$\w*[mM]essage\w*\s*=\s*|buildViolation\(\s*|invalid_message\'\]?\s*(?:=>|\?\?)\s*)$/';

    /**
     * Where a key that is neither of the above (a match arm, a method argument, a returned constant)
     * is read by the validator. Anywhere else it is an exception message.
     */
    private const array VALIDATION_PATHS = ['src/ApiResource/', 'src/Validator/', 'src/Service/BandSpace/RichText/'];

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

    /** @var array<string, string>|null path => code without its comments */
    private static ?array $sources = null;

    /**
     * One key per line, sorted: every area adds keys to these two files at once, and sorted single lines
     * merge. A domain owned by one feature (push) may group its keys as it likes.
     */
    #[DataProvider('sharedCatalogues')]
    public function test_a_shared_catalogue_is_flat_and_sorted(string $file): void
    {
        $messages = Yaml::parseFile(self::PROJECT_DIR . '/translations/' . $file) ?? [];
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
     * A key read both by the validator and by an exception is one message: its two copies must agree.
     */
    public function test_a_key_in_both_catalogues_has_the_same_text(): void
    {
        $messages = self::load(self::MESSAGES);
        $different = [];
        foreach (array_intersect_key(self::load(self::VALIDATORS), $messages) as $key => $text) {
            if ($messages[$key] !== $text) {
                $different[] = $key;
            }
        }

        self::assertSame([], $different);
    }

    /**
     * ICU reads an apostrophe before `{` as the start of a quoted literal, and French is full of
     * apostrophes: `d'{name}` renders `d{name}`, `d''{name}` is how to write it. A simple message must
     * render as written once `''` reads as `'` and each placeholder holds its value.
     */
    #[DataProvider('icuCatalogues')]
    public function test_every_simple_icu_message_renders_as_written(string $file): void
    {
        $formatter = new IntlFormatter();
        foreach (self::load($file) as $key => $message) {
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
    #[DataProvider('icuCatalogues')]
    public function test_every_plural_or_select_icu_message_formats(string $file): void
    {
        $formatter = new IntlFormatter();
        $empty = [];
        foreach (self::load($file) as $key => $message) {
            if (!self::hasComplexArgument($message)) {
                continue;
            }

            foreach ([1, 1000] as $number) {
                if ($formatter->formatIntl($message, 'fr', array_fill_keys(self::placeholders($message), $number)) === '') {
                    $empty[] = $key;
                }
            }
        }

        self::assertSame([], $empty);
    }

    #[DataProvider('catalogues')]
    public function test_every_key_is_used_in_src(string $file): void
    {
        preg_match_all('/[\'"](' . self::KEY . ')[\'"]/', implode("\n", self::sources()), $matches);
        $literals = array_flip($matches[1]);

        $unused = array_keys(array_diff_key(self::load($file), $literals));

        self::assertSame([], $unused, $file . ' holds keys that nothing in src/ uses');
    }

    /**
     * Every literal in a namespace the catalogues use is a key, and it is in the catalogue of whatever
     * reads it: `validators` for the validator, `messages` for an exception, and the domain a file names
     * (`'push'`) for the other keys of that file.
     */
    public function test_every_key_in_src_is_in_the_catalogue_that_reads_it(): void
    {
        $catalogues = [];
        foreach (self::catalogueFiles() as $file) {
            $catalogues[$file] = self::load($file);
        }

        $missing = [];
        foreach (self::keySites() as [$path, $key, $files]) {
            if (array_filter($files, static fn (string $file): bool => isset($catalogues[$file][$key])) === []) {
                $missing[] = implode(' or ', $files) . ': ' . $key . ' (' . $path . ')';
            }
        }

        self::assertSame([], array_values(array_unique($missing)));
    }

    /** has() walks the fallback chain, so a French name entry would shadow the database name in every locale. */
    public function test_the_french_reference_catalogue_holds_no_reference_name(): void
    {
        $names = preg_grep('/^(?:instrument|instrument_musician|style|publication_sub_category)\./', array_keys(self::load(self::REFERENCE)));

        self::assertSame([], array_values($names));
    }

    /**
     * A message with a placeholder only renders through an exception that carries its parameters: any
     * other class would show `{name}` to the user.
     */
    public function test_a_key_with_a_placeholder_is_thrown_with_its_parameters(): void
    {
        $messages = self::load(self::MESSAGES);
        $code = implode("\n", self::sources());
        preg_match_all('/class (\w+)[^{]*\bimplements\b[^{]*\bTranslatableInterface\b/', $code, $translatable);

        preg_match_all('/new ([\w\\\\]*Exception)\(\s*(?:[^;]{0,80}?,\s*)?[\'"](' . self::KEY . ')[\'"]/', $code, $throws, PREG_SET_ORDER);
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
     * A parameter the message does not name is lost, and a placeholder no parameter fills shows as
     * `{name}`: the names on both sides must be the same.
     */
    public function test_the_parameters_of_a_translatable_exception_name_its_placeholders(): void
    {
        $messages = self::load(self::MESSAGES);
        $mismatched = [];
        $checked = 0;
        foreach (self::translatableThrows() as [$path, $key, $parameters]) {
            // A key that is not a literal here (TranslatableHttpException's own parent call) is not checked.
            if ($key === '') {
                continue;
            }

            $placeholders = self::placeholders($messages[$key] ?? '');
            sort($placeholders);
            sort($parameters);
            if ($placeholders !== $parameters) {
                $mismatched[] = $key . ' (' . $path . '): [' . implode(', ', $parameters) . '] for {' . implode('}, {', $placeholders) . '}';
            }
            ++$checked;
        }

        self::assertGreaterThan(0, $checked);
        self::assertSame([], $mismatched);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function catalogues(): iterable
    {
        foreach (self::catalogueFiles() as $file) {
            yield $file => [$file];
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function icuCatalogues(): iterable
    {
        foreach (self::catalogueFiles() as $file) {
            if (str_contains($file, '+intl-icu.')) {
                yield $file => [$file];
            }
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function sharedCatalogues(): iterable
    {
        yield self::MESSAGES => [self::MESSAGES];
        yield self::VALIDATORS => [self::VALIDATORS];
    }

    /**
     * @return list<string> every French catalogue file, `messages+intl-icu.fr.yaml` and the like
     */
    private static function catalogueFiles(): array
    {
        $files = array_map('basename', glob(self::PROJECT_DIR . '/translations/*.fr.yaml') ?: []);
        sort($files);

        return $files;
    }

    /**
     * Each key literal in src/ (repositories aside) with the catalogues that may hold it. An exception
     * or a constraint reads it from its own catalogue, wherever the key is written or wherever the
     * constant holding it is used. Any other key is read from the domain its file names, if any
     * (`private const string DOMAIN = 'push'`), else from the catalogue its path implies.
     *
     * @return list<array{string, string, list<string>}> path, key, catalogue files
     */
    private static function keySites(): array
    {
        $domains = [];
        $catalogueKeys = [];
        foreach (self::catalogueFiles() as $file) {
            $domains[(string) preg_replace('/(?:\+intl-icu)?\.fr\.yaml$/', '', $file)] = $file;
            $catalogueKeys = [...$catalogueKeys, ...array_keys(self::load($file))];
        }
        $otherDomains = array_diff_key($domains, array_flip(['messages', 'validators']));
        $namespaces = array_unique(array_map(static fn (string $key): string => explode('.', $key)[0], $catalogueKeys));
        $literal = '/[\'"]((?:' . implode('|', $namespaces) . ')(?:\.[a-z0-9_]+)+)[\'"]/';
        $sources = array_filter(self::sources(), static fn (string $path): bool => !str_starts_with($path, 'src/Repository/'), ARRAY_FILTER_USE_KEY);

        $sites = [];
        foreach ($sources as $path => $code) {
            $namedDomains = array_values(array_filter(
                $otherDomains,
                static fn (string $domain): bool => preg_match('/[\'"]' . preg_quote($domain, '/') . '[\'"]/', $code) === 1,
                ARRAY_FILTER_USE_KEY,
            ));
            preg_match_all($literal, $code, $matches, PREG_OFFSET_CAPTURE);
            foreach ($matches[1] as [$key, $offset]) {
                if (in_array($key, self::NOT_KEYS, true)) {
                    continue;
                }

                $before = substr($code, max(0, $offset - 121), min($offset - 1, 120));
                $catalogues = preg_match('/const string (\w+)\s*=\s*$/', $before, $constant) === 1
                    ? self::cataloguesReadingConstant($constant[1], $sources)
                    : array_filter([self::catalogueReading($before)]);

                if ($catalogues === []) {
                    $sites[] = [$path, $key, $namedDomains ?: [self::catalogueByPath($path)]];
                }
                foreach ($catalogues as $catalogue) {
                    $sites[] = [$path, $key, [$catalogue]];
                }
            }
        }

        return $sites;
    }

    private static function catalogueReading(string $before): ?string
    {
        return match (true) {
            preg_match(self::EXCEPTION_ARGUMENT, $before) === 1 => self::MESSAGES,
            preg_match(self::CONSTRAINT_ARGUMENT, $before) === 1 => self::VALIDATORS,
            default => null,
        };
    }

    /**
     * @param array<string, string> $sources
     *
     * @return list<string>
     */
    private static function cataloguesReadingConstant(string $name, array $sources): array
    {
        $catalogues = [];
        foreach ($sources as $code) {
            preg_match_all('/::' . $name . '\b/', $code, $uses, PREG_OFFSET_CAPTURE);
            foreach ($uses[0] as [, $offset]) {
                $before = preg_replace('/(?:self|static|[\w\\\\]+)$/', '', substr($code, max(0, $offset - 140), min($offset, 140)));
                $catalogues[] = self::catalogueReading((string) $before);
            }
        }

        return array_values(array_unique(array_filter($catalogues)));
    }

    private static function catalogueByPath(string $path): string
    {
        foreach (self::VALIDATION_PATHS as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return self::VALIDATORS;
            }
        }

        return self::MESSAGES;
    }

    /**
     * The key and the parameter names of every `new TranslatableHttpException(status, 'key', [...])`,
     * and of every other exception using TranslatableExceptionTrait (`parent::__construct('key')` with
     * `$this->parameters = [...]`).
     *
     * @return list<array{string, string, list<string>}>
     */
    private static function translatableThrows(): array
    {
        $throws = [];
        foreach (self::sources() as $path => $code) {
            if (!str_contains($code, 'TranslatableHttpException(') && !str_contains($code, 'use TranslatableExceptionTrait;')) {
                continue;
            }

            $tokens = self::tokens($code);
            foreach ($tokens as $i => $token) {
                if (self::text($token) === 'new' && str_ends_with(self::text($tokens[$i + 1] ?? ''), 'TranslatableHttpException')) {
                    $arguments = self::arguments($tokens, $i + 2);
                    $throws[] = [$path, self::stringValue($arguments[1] ?? []), self::arrayKeys($arguments[2] ?? [])];
                }
                if (self::text($token) === 'parent' && self::text($tokens[$i + 2] ?? '') === '__construct' && str_contains($code, 'use TranslatableExceptionTrait;')) {
                    $parameters = preg_match('/\$this->parameters\s*=\s*\[/', $code, $match, PREG_OFFSET_CAPTURE) === 1
                        ? self::arrayKeys(self::tokens(substr($code, $match[0][1] + strlen($match[0][0]) - 1)))
                        : [];
                    $throws[] = [$path, self::stringValue(self::arguments($tokens, $i + 3)[0] ?? []), $parameters];
                }
            }
        }

        return $throws;
    }

    /**
     * @return list<array{0: int, 1: string, 2: int}|string>
     */
    private static function tokens(string $code): array
    {
        $tokens = token_get_all(str_starts_with($code, '<?php') ? $code : '<?php ' . $code);

        return array_values(array_filter($tokens, static fn (array|string $token): bool => !is_array($token) || !in_array($token[0], [T_OPEN_TAG, T_WHITESPACE], true)));
    }

    /**
     * @param array{0: int, 1: string, 2: int}|string $token
     */
    private static function text(array|string $token): string
    {
        return is_array($token) ? $token[1] : $token;
    }

    /**
     * The tokens of each argument of the call whose `(` is at $open.
     *
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     *
     * @return list<list<array{0: int, 1: string, 2: int}|string>>
     */
    private static function arguments(array $tokens, int $open): array
    {
        $arguments = [[]];
        $depth = 0;
        for ($i = $open, $count = count($tokens); $i < $count; ++$i) {
            $text = self::text($tokens[$i]);
            if (in_array($text, ['(', '[', '{', '{$', '${'], true)) {
                if (++$depth === 1) {
                    continue;
                }
            } elseif (in_array($text, [')', ']', '}'], true)) {
                if (--$depth === 0) {
                    break;
                }
            } elseif ($text === ',' && $depth === 1) {
                $arguments[] = [];
                continue;
            }
            $arguments[count($arguments) - 1][] = $tokens[$i];
        }

        return array_values(array_filter($arguments));
    }

    /**
     * @param list<array{0: int, 1: string, 2: int}|string> $argument
     */
    private static function stringValue(array $argument): string
    {
        return count($argument) === 1 && is_array($argument[0]) && $argument[0][0] === T_CONSTANT_ENCAPSED_STRING
            ? substr($argument[0][1], 1, -1)
            : '';
    }

    /**
     * The quoted keys of an array literal, its own level only (a closure's `=>` inside a value is not one).
     *
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens starting at the array's `[`
     *
     * @return list<string>
     */
    private static function arrayKeys(array $tokens): array
    {
        if (self::text($tokens[0] ?? '') !== '[') {
            return [];
        }

        $keys = [];
        foreach (self::arguments($tokens, 0) as $element) {
            if (count($element) > 1 && is_array($element[0]) && $element[0][0] === T_CONSTANT_ENCAPSED_STRING && self::text($element[1]) === '=>') {
                $keys[] = substr($element[0][1], 1, -1);
            }
        }

        return $keys;
    }

    /**
     * @return list<string>
     */
    private static function placeholders(string $message): array
    {
        preg_match_all('/\{\s*(\w+)\s*[,}]/', $message, $matches);

        return array_values(array_unique($matches[1]));
    }

    private static function hasComplexArgument(string $message): bool
    {
        return preg_match('/\{\s*\w+\s*,\s*(?:plural|select|selectordinal|number)\b/', $message) === 1;
    }

    /**
     * The catalogue as the translator reads it: nested keys joined with dots.
     *
     * @return array<string, string>
     */
    private static function load(string $file): array
    {
        return self::flatten(Yaml::parseFile(self::PROJECT_DIR . '/translations/' . $file) ?? []);
    }

    /**
     * @param array<mixed> $messages
     *
     * @return array<string, string>
     */
    private static function flatten(array $messages, string $prefix = ''): array
    {
        $flat = [];
        foreach ($messages as $key => $message) {
            $flat += is_array($message) ? self::flatten($message, $prefix . $key . '.') : [$prefix . $key => (string) $message];
        }

        return $flat;
    }

    /**
     * Every PHP file of src/, by path from the project root, with its comments removed so a key quoted
     * in a docblock neither counts as used nor as handed to anything.
     *
     * @return array<string, string>
     */
    private static function sources(): array
    {
        if (self::$sources !== null) {
            return self::$sources;
        }

        $root = realpath(self::PROJECT_DIR) . '/';
        $sources = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . 'src')) as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $code = '';
            foreach (token_get_all((string) file_get_contents($file->getPathname())) as $token) {
                if (!is_array($token) || !in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    $code .= self::text($token);
                }
            }
            $sources[substr($file->getPathname(), strlen($root))] = $code;
        }
        ksort($sources);

        return self::$sources = $sources;
    }
}
