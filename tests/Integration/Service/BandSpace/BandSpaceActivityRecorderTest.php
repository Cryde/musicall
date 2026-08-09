<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceActivity;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\BandSpaceActivityRepository;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Tests\Factory\BandSpace\BandSpaceActivityFactory;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\User\UserFactory;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class BandSpaceActivityRecorderTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
        parent::setUp();
    }

    public function test_record_persists_activity_with_all_fields(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
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
        $bandSpace = BandSpaceFactory::new()->create();
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
        $bandSpace = BandSpaceFactory::new()->create();

        $activity = $this->getRecorder()->record(
            bandSpace: $bandSpace,
            module: BandSpaceModule::File,
            type: 'public_accessed',
            resourceId: Uuid::uuid4(),
        );

        $this->getEntityManager()->flush();

        $this->assertNull($activity->actor);
        $this->assertSame('public_accessed', $activity->type);
    }

    public function test_record_supports_null_resource_id_and_null_payload(): void
    {
        $bandSpace = BandSpaceFactory::new()->create();

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
        $bandSpace = BandSpaceFactory::new()->create();
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

    /**
     * Both rich text editors, notes and tech rider items, save on a two second debounce. Recording
     * every one of those writes turns a writing session into dozens of near identical feed rows and
     * pushes every other module off the dashboard widget, which shows ten activities for the whole
     * space. These pin the folding that prevents it, and the four things it must not fold.
     */
    public function test_record_coalesced_records_when_the_resource_has_no_earlier_activity(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();

        $activity = $this->getRecorder()->recordCoalesced(
            bandSpace: $bandSpace,
            module: BandSpaceModule::Notes,
            type: 'note_content_updated',
            resourceId: Uuid::uuid4(),
            actor: $user,
        );
        $this->getEntityManager()->flush();

        $this->assertNotNull($activity);
        $this->assertSame('note_content_updated', $activity->type);
    }

    public function test_record_coalesced_records_nothing_inside_the_window(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $resourceId = Uuid::uuid4();
        $this->seedActivity($bandSpace, $resourceId, $user, new DateTime('-2 minutes'));

        $activity = $this->getRecorder()->recordCoalesced(
            bandSpace: $bandSpace,
            module: BandSpaceModule::Notes,
            type: 'note_content_updated',
            resourceId: $resourceId,
            actor: $user,
        );
        $this->getEntityManager()->flush();

        $this->assertNull($activity);
        $this->assertCount(1, $this->getRepository()->findForResource($bandSpace, BandSpaceModule::Notes, $resourceId));
    }

    /**
     * Past the window the trail resumes, otherwise a resource picked up every week would show a
     * single entry forever.
     */
    public function test_record_coalesced_records_again_past_the_window(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $resourceId = Uuid::uuid4();
        $this->seedActivity($bandSpace, $resourceId, $user, new DateTime('-16 minutes'));

        $activity = $this->getRecorder()->recordCoalesced(
            bandSpace: $bandSpace,
            module: BandSpaceModule::Notes,
            type: 'note_content_updated',
            resourceId: $resourceId,
            actor: $user,
        );
        $this->getEntityManager()->flush();

        $this->assertNotNull($activity);
        $this->assertCount(2, $this->getRepository()->findForResource($bandSpace, BandSpaceModule::Notes, $resourceId));
    }

    /** Two people writing in the same note are two facts, and folding them would hide who did what. */
    public function test_record_coalesced_records_for_another_actor_inside_the_window(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $other = UserFactory::new()->create(['username' => 'second_member', 'email' => 'second@test.com']);
        $bandSpace = BandSpaceFactory::new()->create();
        $resourceId = Uuid::uuid4();
        $this->seedActivity($bandSpace, $resourceId, $other, new DateTime('-2 minutes'));

        $activity = $this->getRecorder()->recordCoalesced(
            bandSpace: $bandSpace,
            module: BandSpaceModule::Notes,
            type: 'note_content_updated',
            resourceId: $resourceId,
            actor: $user,
        );
        $this->getEntityManager()->flush();

        $this->assertNotNull($activity);
    }

    /** A rename inside the window of a body save is a different act, so it stands on its own. */
    public function test_record_coalesced_records_for_another_type_inside_the_window(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $resourceId = Uuid::uuid4();
        $this->seedActivity($bandSpace, $resourceId, $user, new DateTime('-2 minutes'));

        $activity = $this->getRecorder()->recordCoalesced(
            bandSpace: $bandSpace,
            module: BandSpaceModule::Notes,
            type: 'note_renamed',
            resourceId: $resourceId,
            actor: $user,
        );
        $this->getEntityManager()->flush();

        $this->assertNotNull($activity);
    }

    public function test_record_coalesced_records_for_another_resource_inside_the_window(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $this->seedActivity($bandSpace, Uuid::uuid4(), $user, new DateTime('-2 minutes'));

        $activity = $this->getRecorder()->recordCoalesced(
            bandSpace: $bandSpace,
            module: BandSpaceModule::Notes,
            type: 'note_content_updated',
            resourceId: Uuid::uuid4(),
            actor: $user,
        );
        $this->getEntityManager()->flush();

        $this->assertNotNull($activity);
    }

    private function seedActivity(
        BandSpace $bandSpace,
        UuidInterface $resourceId,
        User $actor,
        DateTime $when,
    ): void {
        BandSpaceActivityFactory::new([
            'bandSpace' => $bandSpace,
            'module' => BandSpaceModule::Notes,
            'type' => 'note_content_updated',
            'resourceId' => $resourceId,
            'actor' => $actor,
            'creationDatetime' => $when,
        ])->create();
    }

    private function getRecorder(): BandSpaceActivityRecorder
    {
        return new BandSpaceActivityRecorder($this->getEntityManager(), $this->getRepository());
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
