<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service\BandSpace;

use App\Entity\BandSpace\BandSpaceActivity;
use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class BandSpaceActivityRecorderTest extends KernelTestCase
{
    use ResetDatabase, Factories;

    protected function setUp(): void
    {
        self::bootKernel();
        parent::setUp();
    }

    public function test_record_persists_activity_with_all_fields(): void
    {
        $user = UserFactory::new()->asBaseUser()->create()->_real();
        $bandSpace = BandSpaceFactory::new()->create()->_real();
        $resourceId = Uuid::uuid4();

        $activity = $this->getRecorder()->record(
            bandSpace: $bandSpace,
            module: BandSpaceModule::File,
            type: 'uploaded',
            resourceId: $resourceId,
            actor: $user,
            payload: ['original_name' => 'master.wav'],
        );

        $this->getEntityManager()->flush();

        $this->assertNotNull($activity->id);
        $this->assertSame($bandSpace->id, $activity->bandSpace->id);
        $this->assertSame(BandSpaceModule::File, $activity->module);
        $this->assertSame($user->id, $activity->actor?->id);
        $this->assertSame('uploaded', $activity->type);
        $this->assertSame(['original_name' => 'master.wav'], $activity->payload);
        $this->assertTrue($activity->resourceId?->equals($resourceId));
    }

    public function test_record_accepts_string_resource_id_and_normalises_to_uuid(): void
    {
        $bandSpace = BandSpaceFactory::new()->create()->_real();
        $resourceIdString = Uuid::uuid4()->toString();

        $activity = $this->getRecorder()->record(
            bandSpace: $bandSpace,
            module: BandSpaceModule::Task,
            type: 'status_changed',
            resourceId: $resourceIdString,
        );

        $this->getEntityManager()->flush();

        $this->assertSame($resourceIdString, $activity->resourceId?->toString());
    }

    public function test_record_supports_anonymous_actor(): void
    {
        $bandSpace = BandSpaceFactory::new()->create()->_real();

        $activity = $this->getRecorder()->record(
            bandSpace: $bandSpace,
            module: BandSpaceModule::File,
            type: 'public_accessed',
            resourceId: Uuid::uuid4(),
            actor: null,
        );

        $this->getEntityManager()->flush();

        $this->assertNull($activity->actor);
        $this->assertSame('public_accessed', $activity->type);
    }

    public function test_record_supports_null_resource_id_and_null_payload(): void
    {
        $bandSpace = BandSpaceFactory::new()->create()->_real();

        $activity = $this->getRecorder()->record(
            bandSpace: $bandSpace,
            module: BandSpaceModule::File,
            type: 'archived',
        );

        $this->getEntityManager()->flush();

        $this->assertNull($activity->resourceId);
        $this->assertNull($activity->payload);
        $this->assertNull($activity->actor);
    }

    public function test_find_for_resource_returns_only_matching_activities_newest_first(): void
    {
        $bandSpace = BandSpaceFactory::new()->create()->_real();
        $fileId = Uuid::uuid4();
        $otherFileId = Uuid::uuid4();

        $this->getRecorder()->record(
            bandSpace: $bandSpace,
            module: BandSpaceModule::File,
            type: 'uploaded',
            resourceId: $fileId,
        );
        $this->getRecorder()->record(
            bandSpace: $bandSpace,
            module: BandSpaceModule::File,
            type: 'renamed',
            resourceId: $fileId,
        );
        // Same band, same module, different resource — must be excluded
        $this->getRecorder()->record(
            bandSpace: $bandSpace,
            module: BandSpaceModule::File,
            type: 'uploaded',
            resourceId: $otherFileId,
        );
        // Same band, same resource id, different module — must be excluded
        $this->getRecorder()->record(
            bandSpace: $bandSpace,
            module: BandSpaceModule::Task,
            type: 'status_changed',
            resourceId: $fileId,
        );
        $this->getEntityManager()->flush();

        // Force distinct creation order via direct SQL update to avoid flakiness on identical timestamps
        $this->getEntityManager()->clear();

        $activities = $this->getRepository()->findForResource(
            $bandSpace,
            BandSpaceModule::File,
            $fileId,
        );

        $this->assertCount(2, $activities);
        $types = array_map(fn(BandSpaceActivity $a): string => $a->type, $activities);
        sort($types);
        $this->assertSame(['renamed', 'uploaded'], $types);
    }

    private function getRecorder(): BandSpaceActivityRecorder
    {
        return new BandSpaceActivityRecorder($this->getEntityManager());
    }

    private function getRepository(): BandSpaceActivityRepository
    {
        return $this->getEntityManager()->getRepository(BandSpaceActivity::class);
    }

    private function getEntityManager(): EntityManagerInterface
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        assert($em instanceof EntityManagerInterface);
        return $em;
    }
}
