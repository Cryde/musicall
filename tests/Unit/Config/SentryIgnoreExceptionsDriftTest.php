<?php

declare(strict_types=1);

namespace App\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerException;
use Symfony\Component\Yaml\Yaml;

/**
 * A class mapped to a 4xx in api_platform.yaml has to be ignored by Sentry too (#1391), or each of
 * its expected refusals lands in Sentry errors again. The two lists live in two files, hence this.
 */
class SentryIgnoreExceptionsDriftTest extends TestCase
{
    /**
     * Mapped to a 4xx and still reported on purpose: a broken output normalizer throws it too, and
     * that one is a bug.
     */
    private const array STILL_REPORTED = [SerializerException::class];

    public function test_every_class_mapped_to_a_4xx_is_ignored_by_sentry(): void
    {
        /** @var list<string> $ignored */
        $ignored = $this->parse('sentry.yaml')['when@prod']['sentry']['options']['ignore_exceptions'];
        /** @var array<string, int> $exceptionToStatus */
        $exceptionToStatus = $this->parse('api_platform.yaml')['api_platform']['exception_to_status'];

        $uncovered = [];
        foreach ($exceptionToStatus as $class => $status) {
            if ($status < 400 || $status >= 500 || in_array($class, self::STILL_REPORTED, true)) {
                continue;
            }
            // Sentry matches with is_a(), so an ignored parent covers the class.
            if (!array_any($ignored, static fn (string $ignoredClass): bool => is_a($class, $ignoredClass, true))) {
                $uncovered[] = $class;
            }
        }

        $this->assertSame([], $uncovered, 'Mapped to a 4xx in api_platform.yaml but missing from ignore_exceptions in sentry.yaml');
    }

    /**
     * is_a() against a class that does not exist matches nothing, so a typo would quietly send a 4xx back.
     */
    public function test_every_ignored_exception_exists(): void
    {
        /** @var list<string> $ignored */
        $ignored = $this->parse('sentry.yaml')['when@prod']['sentry']['options']['ignore_exceptions'];

        $missing = array_values(array_filter($ignored, static fn (string $class): bool => !class_exists($class) && !interface_exists($class)));

        $this->assertSame([], $missing);
    }

    /**
     * @return array<string, mixed>
     */
    private function parse(string $configFile): array
    {
        // sentry.yaml holds a `!php/const`.
        return Yaml::parseFile(dirname(__DIR__, 3) . '/config/packages/' . $configFile, Yaml::PARSE_CONSTANT);
    }
}
