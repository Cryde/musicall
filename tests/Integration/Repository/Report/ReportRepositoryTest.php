<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository\Report;

use App\Enum\Report\ReportOutcome;
use App\Repository\Report\ReportRepository;
use App\Tests\Factory\Report\ReportFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class ReportRepositoryTest extends KernelTestCase
{
    /**
     * Only the reports the moderator's decision was about are closed (#1125): one filed on the same
     * content while they were deciding stays pending, since its reporter is not told of a decision.
     */
    public function test_resolving_closes_only_the_reports_given(): void
    {
        $moderator = UserFactory::new()->asAdminUser()->create();
        $decided = ReportFactory::new(['targetId' => 'b6f1a6a0-0000-4000-8000-000000000001'])->create();
        $filedMeanwhile = ReportFactory::new(['targetId' => 'b6f1a6a0-0000-4000-8000-000000000001'])->create();
        $repository = self::getContainer()->get(ReportRepository::class);

        $repository->resolve([$decided], $moderator, ReportOutcome::Dismissed);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $this->assertSame(ReportOutcome::Dismissed, $repository->find($decided->id)?->outcome);
        $this->assertTrue($repository->find($filedMeanwhile->id)?->isPending());
    }
}
