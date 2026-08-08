<?php declare(strict_types=1);

namespace App\Tests\Integration\Command\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\User;
use App\Repository\BandSpace\BandSpaceFileAttachmentRepository;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceNoteFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileAttachmentFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileFactory;
use App\Tests\Factory\BandSpace\SetlistFactory;
use App\Tests\Factory\BandSpace\SongFactory;
use App\Tests\Factory\BandSpace\TaskFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Zenstruck\Foundry\Attribute\ResetDatabase;


#[ResetDatabase]
class PruneOrphanFileAttachmentsCommandTest extends KernelTestCase
{
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        self::bootKernel();
        parent::setUp();

        $application = new Application(self::$kernel);
        $command = $application->find('app:band-space:prune-orphan-attachments');
        $this->commandTester = new CommandTester($command);
    }

    public function test_removes_only_attachments_whose_source_is_gone(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();

        $liveTask = TaskFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        $liveNote = BandSpaceNoteFactory::new(['bandSpace' => $bandSpace])->create();

        $orphanTaskAttachmentId = $this->attach($bandSpace, $user, 'task', (string) Uuid::uuid4());
        $orphanNoteAttachmentId = $this->attach($bandSpace, $user, 'note', (string) Uuid::uuid4());
        $liveTaskAttachmentId = $this->attach($bandSpace, $user, 'task', (string) $liveTask->id);
        $liveNoteAttachmentId = $this->attach($bandSpace, $user, 'note', (string) $liveNote->id);

        $this->commandTester->execute([]);
        $this->commandTester->assertCommandIsSuccessful();

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('task: 1 orphan attachment(s)', $output);
        $this->assertStringContainsString('note: 1 orphan attachment(s)', $output);
        $this->assertStringContainsString('Deleted 2 orphan attachment(s)', $output);

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $repository = self::getContainer()->get(BandSpaceFileAttachmentRepository::class);
        $this->assertNull($repository->find($orphanTaskAttachmentId));
        $this->assertNull($repository->find($orphanNoteAttachmentId));
        $this->assertNotNull($repository->find($liveTaskAttachmentId));
        $this->assertNotNull($repository->find($liveNoteAttachmentId));
    }

    public function test_keeps_attachments_of_soft_deleted_songs_and_setlists(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();

        $archivedSong = SongFactory::new([
            'bandSpace' => $bandSpace,
            'archiveDatetime' => new \DateTimeImmutable('-2 days'),
        ])->create();
        $archivedSetlist = SetlistFactory::new([
            'bandSpace' => $bandSpace,
            'archiveDatetime' => new \DateTimeImmutable('-2 days'),
        ])->create();
        $archivedTask = TaskFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $user,
            'archiveDatetime' => new \DateTimeImmutable('-2 days'),
        ])->create();

        $songAttachmentId = $this->attach($bandSpace, $user, 'song', (string) $archivedSong->id);
        $setlistAttachmentId = $this->attach($bandSpace, $user, 'setlist', (string) $archivedSetlist->id);
        $taskAttachmentId = $this->attach($bandSpace, $user, 'task', (string) $archivedTask->id);

        $this->commandTester->execute([]);
        $this->commandTester->assertCommandIsSuccessful();
        $this->assertStringContainsString('Deleted 0 orphan attachment(s)', $this->commandTester->getDisplay());

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $repository = self::getContainer()->get(BandSpaceFileAttachmentRepository::class);
        $this->assertNotNull($repository->find($songAttachmentId), 'An archived song still has its row, so it is not an orphan');
        $this->assertNotNull($repository->find($setlistAttachmentId));
        $this->assertNotNull($repository->find($taskAttachmentId));
    }

    public function test_dry_run_reports_without_deleting_and_the_run_is_repeatable(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $orphanId = $this->attach($bandSpace, $user, 'finance', (string) Uuid::uuid4());

        $this->commandTester->execute(['--dry-run' => true]);
        $this->commandTester->assertCommandIsSuccessful();
        $this->assertStringContainsString('Would delete 1 orphan attachment(s)', $this->commandTester->getDisplay());

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $repository = self::getContainer()->get(BandSpaceFileAttachmentRepository::class);
        $this->assertNotNull($repository->find($orphanId));

        $this->commandTester->execute([]);
        $this->assertStringContainsString('Deleted 1 orphan attachment(s)', $this->commandTester->getDisplay());

        $this->commandTester->execute([]);
        $this->commandTester->assertCommandIsSuccessful();
        $this->assertStringContainsString('Deleted 0 orphan attachment(s)', $this->commandTester->getDisplay());
    }

    public function test_leaves_the_file_itself_alone(): void
    {
        $user = UserFactory::new()->asBaseUser()->create();
        $bandSpace = BandSpaceFactory::new()->create();
        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();
        BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $file,
            'sourceType' => 'task',
            'sourceId' => Uuid::uuid4(),
            'attachedBy' => $user,
        ]);
        $fileId = (string) $file->id;

        $this->commandTester->execute([]);
        $this->commandTester->assertCommandIsSuccessful();

        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $reloaded = self::getContainer()->get(BandSpaceFileRepository::class)->find($fileId);
        $this->assertNotNull($reloaded);
        $this->assertNull($reloaded->archiveDatetime);
    }

    private function attach(BandSpace $bandSpace, User $user, string $sourceType, string $sourceId): string
    {
        $file = BandSpaceFileFactory::new(['bandSpace' => $bandSpace, 'createdBy' => $user])->create();

        return (string) BandSpaceFileAttachmentFactory::createOne([
            'bandSpaceFile' => $file,
            'sourceType' => $sourceType,
            'sourceId' => Uuid::fromString($sourceId),
            'attachedBy' => $user,
        ])->id;
    }
}
