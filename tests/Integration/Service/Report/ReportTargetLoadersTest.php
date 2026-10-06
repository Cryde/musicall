<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service\Report;

use App\Enum\Report\ReportTargetType;
use App\Service\Report\Target\ReportTargetLoaderInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ReportTargetLoadersTest extends KernelTestCase
{
    /** A type without a loader would be accepted by the validator and then crash the processor. */
    public function test_every_target_type_has_exactly_one_loader(): void
    {
        self::bootKernel();
        $types = [];
        foreach (glob(self::$kernel->getProjectDir() . '/src/Service/Report/Target/*TargetLoader.php') ?: [] as $file) {
            $loader = self::getContainer()->get('App\\Service\\Report\\Target\\' . basename($file, '.php'));
            $this->assertInstanceOf(ReportTargetLoaderInterface::class, $loader);
            $types[] = $loader->type()->value;
        }

        sort($types);
        $expected = ReportTargetType::values();
        sort($expected);
        $this->assertSame($expected, $types);
    }
}
