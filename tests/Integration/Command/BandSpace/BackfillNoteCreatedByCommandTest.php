<?php declare(strict_types=1);

namespace App\Tests\Integration\Command\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceNote;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceModule;
use App\Enum\BandSpace\BandSpaceNoteActivityType;
use App\Repository\BandSpace\BandSpaceNoteRepository;
use App\Tests\Factory\BandSpace\BandSpaceActivityFactory;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceNoteFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class BackfillNoteCreatedByCommandTest extends KernelTestCase
{
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        self::bootKernel();
        parent::setUp();

        $application = new Application(self::$kernel);
        $command = $application->find('app:band-space:notes:backfill-created-by');
        $this->commandTester = new CommandTester($command);
    }

    public function test_fills_the_author_from_the_note_created_activity(): void
    {
        $author = $this->createMember('note_author');
        $bandSpace = BandSpaceFactory::new()->create();
        $note = BandSpaceNoteFactory::new(['bandSpace' => $bandSpace])->create();
        $this->recordCreation($bandSpace, $note, $author);

        $this->commandTester->execute([]);
        $this->commandTester->assertCommandIsSuccessful();

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Notes with nobody recorded: 1', $output);
        $this->assertStringContainsString('Recovered from a note_created activity: 1', $output);
        $this->assertStringContainsString('Older than the activity feed, left as they are: 0', $output);
        $this->assertStringContainsString('1 note(s) now record their author.', $output);

        $this->assertSame((string) $author->id, (string) $this->reload($note)->createdBy?->id);
    }

    public function test_leaves_a_note_whose_only_activity_is_a_later_edit_alone(): void
    {
        $editor = $this->createMember('note_editor');
        $bandSpace = BandSpaceFactory::new()->create();
        $note = BandSpaceNoteFactory::new(['bandSpace' => $bandSpace])->create();

        // The member who edited or renamed a note is not necessarily the one who wrote it, so neither
        // type may stand in for note_created.
        BandSpaceActivityFactory::createOne([
            'bandSpace' => $bandSpace,
            'module' => BandSpaceModule::Notes,
            'type' => BandSpaceNoteActivityType::ContentUpdated->value,
            'resourceId' => Uuid::fromString((string) $note->id),
            'actor' => $editor,
        ]);
        BandSpaceActivityFactory::createOne([
            'bandSpace' => $bandSpace,
            'module' => BandSpaceModule::Notes,
            'type' => BandSpaceNoteActivityType::Renamed->value,
            'resourceId' => Uuid::fromString((string) $note->id),
            'actor' => $editor,
        ]);

        $this->commandTester->execute([]);
        $this->commandTester->assertCommandIsSuccessful();

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Recovered from a note_created activity: 0', $output);
        $this->assertStringContainsString('Older than the activity feed, left as they are: 1', $output);

        $this->assertNull($this->reload($note)->createdBy);
    }

    public function test_never_overwrites_an_author_already_recorded(): void
    {
        $recordedAuthor = $this->createMember('recorded_author');
        $otherMember = $this->createMember('other_member');
        $bandSpace = BandSpaceFactory::new()->create();
        $note = BandSpaceNoteFactory::new([
            'bandSpace' => $bandSpace,
            'createdBy' => $recordedAuthor,
        ])->create();
        $this->recordCreation($bandSpace, $note, $otherMember);

        $this->commandTester->execute([]);
        $this->commandTester->assertCommandIsSuccessful();
        $this->assertStringContainsString('Every note already records who wrote it.', $this->commandTester->getDisplay());

        $this->assertSame((string) $recordedAuthor->id, (string) $this->reload($note)->createdBy?->id);
    }

    public function test_ignores_an_activity_of_another_module_on_the_same_resource_id(): void
    {
        $member = $this->createMember('note_member');
        $bandSpace = BandSpaceFactory::new()->create();
        $note = BandSpaceNoteFactory::new(['bandSpace' => $bandSpace])->create();

        // resource_id has no foreign key, so the same uuid can legitimately appear under another module.
        BandSpaceActivityFactory::createOne([
            'bandSpace' => $bandSpace,
            'module' => BandSpaceModule::Task,
            'type' => BandSpaceNoteActivityType::Created->value,
            'resourceId' => Uuid::fromString((string) $note->id),
            'actor' => $member,
        ]);

        $this->commandTester->execute([]);
        $this->commandTester->assertCommandIsSuccessful();
        $this->assertStringContainsString('Recovered from a note_created activity: 0', $this->commandTester->getDisplay());

        $this->assertNull($this->reload($note)->createdBy);
    }

    public function test_leaves_a_note_whose_creation_row_has_no_actor_alone(): void
    {
        $bandSpace = BandSpaceFactory::new()->create();
        $note = BandSpaceNoteFactory::new(['bandSpace' => $bandSpace])->create();

        // band_space_activity.actor is nullable, so a creation row can name nobody. It recovers nothing
        // and must not be mistaken for an answer, since this column decides who may delete the note.
        BandSpaceActivityFactory::createOne([
            'bandSpace' => $bandSpace,
            'module' => BandSpaceModule::Notes,
            'type' => BandSpaceNoteActivityType::Created->value,
            'resourceId' => Uuid::fromString((string) $note->id),
            'actor' => null,
        ]);

        $this->commandTester->execute([]);
        $this->commandTester->assertCommandIsSuccessful();

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Recovered from a note_created activity: 0', $output);
        $this->assertStringContainsString('Older than the activity feed, left as they are: 1', $output);

        $this->assertNull($this->reload($note)->createdBy);
    }

    public function test_keeps_the_earliest_actor_when_several_creation_rows_exist(): void
    {
        $firstActor = $this->createMember('first_actor');
        $laterActor = $this->createMember('later_actor');
        $bandSpace = BandSpaceFactory::new()->create();
        $note = BandSpaceNoteFactory::new(['bandSpace' => $bandSpace])->create();

        $this->recordCreation($bandSpace, $note, $laterActor, new \DateTime('2026-06-02 10:00:00'));
        $this->recordCreation($bandSpace, $note, $firstActor, new \DateTime('2026-06-01 10:00:00'));

        $this->commandTester->execute([]);
        $this->commandTester->assertCommandIsSuccessful();
        $this->assertStringContainsString('Recovered from a note_created activity: 1', $this->commandTester->getDisplay());

        $this->assertSame((string) $firstActor->id, (string) $this->reload($note)->createdBy?->id);
    }

    public function test_dry_run_reports_without_writing_and_the_run_is_repeatable(): void
    {
        $author = $this->createMember('note_author');
        $bandSpace = BandSpaceFactory::new()->create();
        $note = BandSpaceNoteFactory::new(['bandSpace' => $bandSpace])->create();
        $this->recordCreation($bandSpace, $note, $author);

        $this->commandTester->execute(['--dry-run' => true]);
        $this->commandTester->assertCommandIsSuccessful();
        $this->assertStringContainsString('[DRY-RUN] 1 note(s) now record their author.', $this->commandTester->getDisplay());
        $this->assertNull($this->reload($note)->createdBy);

        $this->commandTester->execute([]);
        $this->commandTester->assertCommandIsSuccessful();
        $this->assertStringContainsString('1 note(s) now record their author.', $this->commandTester->getDisplay());
        $this->assertSame((string) $author->id, (string) $this->reload($note)->createdBy?->id);

        $this->commandTester->execute([]);
        $this->commandTester->assertCommandIsSuccessful();
        $this->assertStringContainsString('Every note already records who wrote it.', $this->commandTester->getDisplay());
    }

    private function createMember(string $username): User
    {
        return UserFactory::new()->create([
            'username' => $username,
            'email' => $username . '@email.com',
        ]);
    }

    private function recordCreation(
        BandSpace $bandSpace,
        BandSpaceNote $note,
        User $actor,
        ?\DateTime $at = null,
    ): void {
        BandSpaceActivityFactory::createOne([
            'bandSpace' => $bandSpace,
            'module' => BandSpaceModule::Notes,
            'type' => BandSpaceNoteActivityType::Created->value,
            'resourceId' => Uuid::fromString((string) $note->id),
            'actor' => $actor,
            'creationDatetime' => $at ?? new \DateTime('2026-06-01 10:00:00'),
        ]);
    }

    private function reload(BandSpaceNote $note): BandSpaceNote
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();

        $reloaded = self::getContainer()->get(BandSpaceNoteRepository::class)->find((string) $note->id);
        $this->assertInstanceOf(BandSpaceNote::class, $reloaded);

        return $reloaded;
    }
}
