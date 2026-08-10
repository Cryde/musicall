<?php declare(strict_types=1);

namespace App\Tests\Integration\Command\BandSpace;

use App\Entity\BandSpace\BandSpaceNote;
use App\Repository\BandSpace\BandSpaceNoteRepository;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceNoteFactory;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Zenstruck\Foundry\Attribute\ResetDatabase;

#[ResetDatabase]
class RepairNoteContentEncodingCommandTest extends KernelTestCase
{
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        self::bootKernel();
        parent::setUp();

        $application = new Application(self::$kernel);
        $command = $application->find('app:band-space:repair-note-encoding');
        $this->commandTester = new CommandTester($command);
    }

    public function test_reports_without_writing_when_no_flag_is_given(): void
    {
        $noteId = $this->createNote('Répét', $this->doc('C&#039;est l&#039;heure'));

        $this->commandTester->execute([]);
        $this->commandTester->assertCommandIsSuccessful();

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('1 note(s) with a body scanned', $output);
        $this->assertStringContainsString('1 note(s) to repair, attributed node by node', $output);
        $this->assertStringContainsString('0 note(s) to repair with at least one inferred text', $output);
        $this->assertStringContainsString('- C&#039;est l&#039;heure', $output);
        $this->assertStringContainsString("+ C'est l'heure", $output);
        $this->assertStringContainsString('Nothing was written', $output);

        $note = $this->reload($noteId);
        $this->assertSame($this->doc('C&#039;est l&#039;heure'), $note->content);
        $this->assertSame(1, $note->contentVersion);
    }

    public function test_write_repairs_the_body_and_bumps_the_revision(): void
    {
        $noteId = $this->createNote('Répét', $this->doc('C&#039;est l&#039;heure'));

        $this->commandTester->execute(['--write' => true]);
        $this->commandTester->assertCommandIsSuccessful();

        $this->assertStringContainsString('Repaired 1 note(s), 0 of them carrying an inferred text', $this->commandTester->getDisplay());

        $note = $this->reload($noteId);
        $this->assertSame($this->doc("C'est l'heure"), $note->content);
        $this->assertSame(2, $note->contentVersion);
        $this->assertEquals(new DateTime('2026-08-01 09:00:00'), $note->updateDatetime);
    }

    /**
     * The report has to say which repair rests on what. A note whose every encoded text carries a
     * machine only entity needs no reading; one holding a text attributed only by a neighbour does,
     * because a paragraph pasted after #808 shipped has no such neighbour. Both are repaired, and
     * they are never printed in the same list.
     */
    public function test_separates_a_note_attributed_node_by_node_from_one_resting_on_a_sibling(): void
    {
        $attributedId = $this->createNote('Balance', $this->doc('C&#039;est l&#039;heure'));
        $inferredId = $this->createNote('Concert', $this->doc('C&#039;est complet', 'Le concert AC&amp;DC affiche complet'));

        $this->commandTester->execute(['--write' => true]);
        $this->commandTester->assertCommandIsSuccessful();

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('1 note(s) to repair, attributed node by node', $output);
        $this->assertStringContainsString('1 note(s) to repair with at least one inferred text', $output);
        $this->assertStringContainsString('Notes to repair, attributed node by node', $output);
        $this->assertStringContainsString('Notes to repair with at least one inferred text, read these before writing', $output);
        $this->assertStringContainsString('inferred, attributed only by another node of the same note:', $output);
        $this->assertStringContainsString('- Le concert AC&amp;DC affiche complet', $output);
        $this->assertStringContainsString('+ Le concert AC&DC affiche complet', $output);
        $this->assertStringContainsString('Repaired 2 note(s), 1 of them carrying an inferred text', $output);

        $this->assertSame($this->doc("C'est l'heure"), $this->reload($attributedId)->content);
        $this->assertSame(
            $this->doc("C'est complet", 'Le concert AC&DC affiche complet'),
            $this->reload($inferredId)->content,
        );
    }

    /**
     * The sample cap exists so a proven list does not flood the terminal. It must never hide an
     * inferred change, which is the one thing the operator has to read.
     */
    public function test_inferred_changes_are_printed_in_full_whatever_the_sample(): void
    {
        $this->createNote('Concert', $this->doc('C&#039;est complet', 'AC&amp;DC', 'Rock &amp; Roll'));

        $this->commandTester->execute(['--sample' => '1']);
        $this->commandTester->assertCommandIsSuccessful();

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('+ AC&DC', $output);
        $this->assertStringContainsString('+ Rock & Roll', $output);
        $this->assertStringNotContainsString('not shown', $output);
    }

    public function test_a_second_write_run_changes_nothing(): void
    {
        $noteId = $this->createNote('Répét', $this->doc('C&#039;est l&#039;heure'));

        $this->commandTester->execute(['--write' => true]);
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        $this->commandTester->execute(['--write' => true]);
        $this->commandTester->assertCommandIsSuccessful();

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('0 note(s) to repair, attributed node by node', $output);
        $this->assertStringContainsString('0 note(s) to repair with at least one inferred text', $output);
        $this->assertStringContainsString('No note to repair', $output);

        $note = $this->reload($noteId);
        $this->assertSame($this->doc("C'est l'heure"), $note->content);
        $this->assertSame(2, $note->contentVersion);
    }

    public function test_leaves_a_clean_note_and_a_note_needing_review_untouched(): void
    {
        $cleanContent = $this->doc("C'est l'heure de la répét");
        $reviewContent = $this->doc('Pour afficher une esperluette, tapez &amp;');

        $cleanId = $this->createNote('Propre', $cleanContent);
        $reviewId = $this->createNote('Entités HTML', $reviewContent);

        $this->commandTester->execute(['--write' => true]);
        $this->commandTester->assertCommandIsSuccessful();

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('2 note(s) with a body scanned', $output);
        $this->assertStringContainsString('0 note(s) to repair, attributed node by node', $output);
        $this->assertStringContainsString('0 note(s) to repair with at least one inferred text', $output);
        $this->assertStringContainsString('1 note(s) left for a manual review', $output);
        $this->assertStringContainsString('Entités HTML', $output);
        $this->assertStringContainsString('which a member can type by hand', $output);
        $this->assertStringContainsString('Open each one in the editor and repair it by hand', $output);

        $this->assertSame($cleanContent, $this->reload($cleanId)->content);
        $this->assertSame($reviewContent, $this->reload($reviewId)->content);
        $this->assertSame(1, $this->reload($reviewId)->contentVersion);
    }

    public function test_sample_option_caps_the_printed_changes(): void
    {
        $this->createNote('Répét', $this->doc('C&#039;est l&#039;heure', 'On dit &#34;oui&#34;', 'Écrire à x&#64;y.fr'));

        $this->commandTester->execute(['--sample' => '1']);
        $this->commandTester->assertCommandIsSuccessful();

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString("+ C'est l'heure", $output);
        $this->assertStringNotContainsString('+ On dit "oui"', $output);
        $this->assertStringContainsString('2 further text change(s) not shown', $output);
    }

    public function test_a_negative_sample_is_refused(): void
    {
        $this->commandTester->execute(['--sample' => '-1']);

        $this->assertSame(1, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('Option --sample cannot be negative', $this->commandTester->getDisplay());
    }

    /**
     * @param array<string, mixed> $content
     */
    private function createNote(string $title, array $content): string
    {
        $note = BandSpaceNoteFactory::new([
            'bandSpace' => BandSpaceFactory::new(['name' => 'Les Copains']),
            'title' => $title,
            'content' => $content,
            'position' => 0,
            'creationDatetime' => new DateTime('2026-07-01 10:00:00'),
            'updateDatetime' => new DateTime('2026-08-01 09:00:00'),
        ])->create();

        return (string) $note->id;
    }

    private function reload(string $noteId): BandSpaceNote
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        $note = self::getContainer()->get(BandSpaceNoteRepository::class)->find($noteId);
        $this->assertInstanceOf(BandSpaceNote::class, $note);

        return $note;
    }

    /**
     * @return array<string, mixed>
     */
    private function doc(string ...$texts): array
    {
        return [
            'type' => 'doc',
            'content' => array_map(
                static fn(string $text): array => [
                    'type' => 'paragraph',
                    'content' => [['type' => 'text', 'text' => $text]],
                ],
                $texts,
            ),
        ];
    }
}
