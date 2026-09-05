<?php declare(strict_types=1);

namespace App\Tests\Unit\Enum\Feedback;

use App\ApiResource\Feedback\FeedbackResource;
use App\Enum\Feedback\FeedbackModule;
use App\Enum\Feedback\FeedbackType;
use PHPUnit\Framework\TestCase;

/**
 * The drawer prefills the section from the current route and disables its send button below the
 * minimum length, so the module list and the length bound exist in JavaScript as well as in PHP. If
 * the two drift, a user gets a picker offering a value the API refuses, or a send button that
 * enables just before a 422.
 *
 * Same approach as TechRiderDuplicateNameLimitsTest: the duplication is deliberate, and this is the
 * contract between the two copies.
 *
 * Only the literal values live here. Whether the picker actually offers every module is asserted in
 * assets/js/constants/feedback.test.js instead: from PHP the options are `FEEDBACK_MODULES.NAME`
 * identifiers, and a typo in one resolves to undefined at runtime without throwing, so counting them
 * from the source would keep passing while an option was broken.
 */
class FeedbackClientMirrorTest extends TestCase
{
    private const string MODULES_PATH = 'assets/js/utils/feedbackModule.js';
    private const string CONSTANTS_PATH = 'assets/js/constants/feedback.js';

    public function test_the_client_module_list_matches_the_enum(): void
    {
        preg_match_all("/^  [A-Z_]+: '([a-z_]+)'/m", $this->read(self::MODULES_PATH), $matches);
        self::assertNotEmpty($matches[1], sprintf('No FEEDBACK_MODULES entries found in %s.', self::MODULES_PATH));

        $expected = FeedbackModule::values();
        sort($expected);
        $actual = $matches[1];
        sort($actual);

        $this->assertSame($expected, $actual);
    }

    public function test_the_client_type_list_matches_the_enum(): void
    {
        preg_match_all("/\{ value: '([a-z_]+)', label:/", $this->read(self::CONSTANTS_PATH), $matches);
        self::assertNotEmpty($matches[1], sprintf('No FEEDBACK_TYPE_OPTIONS entries found in %s.', self::CONSTANTS_PATH));

        $this->assertSame(FeedbackType::values(), $matches[1]);
    }

    public function test_the_client_minimum_message_length_matches_the_resource(): void
    {
        preg_match('/export const MESSAGE_MIN_LENGTH = (\d+)/', $this->read(self::CONSTANTS_PATH), $matches);
        self::assertNotEmpty($matches, sprintf('No MESSAGE_MIN_LENGTH export found in %s.', self::CONSTANTS_PATH));

        $this->assertSame(FeedbackResource::MESSAGE_MIN_LENGTH, (int) $matches[1]);
    }

    public function test_the_client_maximum_message_length_matches_the_resource(): void
    {
        preg_match('/export const MESSAGE_MAX_LENGTH = (\d+)/', $this->read(self::CONSTANTS_PATH), $matches);
        self::assertNotEmpty($matches, sprintf('No MESSAGE_MAX_LENGTH export found in %s.', self::CONSTANTS_PATH));

        $this->assertSame(FeedbackResource::MESSAGE_MAX_LENGTH, (int) $matches[1]);
    }

    private function read(string $relativePath): string
    {
        // tests/Unit/Enum/Feedback -> project root
        $path = \dirname(__DIR__, 4) . '/' . $relativePath;
        self::assertFileExists($path);

        $source = file_get_contents($path);
        self::assertIsString($source);

        return $source;
    }
}
