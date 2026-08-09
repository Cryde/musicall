<?php declare(strict_types=1);

namespace App\Tests\Unit\Security\BandSpace;

use PHPUnit\Framework\TestCase;

/**
 * A space pending deletion must be read only, and that rule lives in roughly 75 processors. Enumerating
 * them in API tests is not realistic, so this asserts the wiring instead: every processor either goes
 * through a guarded checker variant, guards itself, or is on the allow-list below with its reason.
 *
 * The point is processor number 79. Without this, a new write path silently reopens the hole and no test
 * fails.
 */
class BandSpaceWriteGuardCoverageTest extends TestCase
{
    private const string PROCESSOR_DIR = 'src/State/Processor/BandSpace';

    /**
     * Either of these means the processor is covered: the two guarded checker variants, or the entity
     * predicate for BandSpaceInvitationAcceptProcessor, which has no checker to hang the guard on because
     * its space comes from the invitation rather than from a URI variable.
     */
    private const array GUARD_MARKERS = [
        'checkMemberForWrite(',
        'checkAdminForWrite(',
        'isPendingDeletion(',
    ];

    /**
     * Writes that must keep working while a deletion is pending. Any addition here is a deliberate hole
     * and needs a reason.
     *
     * @var array<string, string>
     */
    private const array ALLOWED_UNGUARDED = [
        'BandSpaceCreateProcessor.php' => 'Creates the space, so there is none to be pending deletion.',
        'BandSpaceDeleteProcessor.php' => 'Schedules the deletion, and carries its own more precise conflict message.',
        'BandSpaceRestoreProcessor.php' => 'Cancels the deletion. Guarding it would make the deletion unrecoverable.',
        'BandSpaceLeaveProcessor.php' => 'A member must be able to walk away from a condemned space.',
        'BandSpaceInvitationDeclineProcessor.php' => 'Declining is the invitee cleaning up their own state.',
        'BandSpaceInvitationDeleteProcessor.php' => 'Revoking only ever takes an invitation away, and accepting one '
            . 'is refused for the whole grace period anyway, so blocking it bought nothing and left an admin unable '
            . 'to tidy up the pending invitations of a condemned space.',
        'BandSpaceMemberUpdateRoleProcessor.php' => 'Leaving requires promoting a successor first, so guarding '
            . 'this would trap the sole admin of a condemned space. Letting them leave without a successor is '
            . 'not the alternative: it would strand the space with nobody able to restore it.',
    ];

    public function test_every_band_space_write_processor_is_guarded_or_explicitly_allowed(): void
    {
        $unguarded = [];
        foreach ($this->processorFiles() as $relativePath => $source) {
            $basename = basename($relativePath);
            if (isset(self::ALLOWED_UNGUARDED[$basename])) {
                continue;
            }

            $isGuarded = array_filter(
                self::GUARD_MARKERS,
                static fn(string $marker): bool => str_contains($source, $marker),
            ) !== [];

            if (!$isGuarded) {
                $unguarded[] = $relativePath;
            }
        }

        self::assertSame([], $unguarded, sprintf(
            "These processors write to a band space without the pending-deletion guard.\n"
            . "Use checkMemberForWrite() or checkAdminForWrite() instead of the read-only variants, or add the\n"
            . "file to ALLOWED_UNGUARDED in %s with a reason.\n",
            self::class,
        ));
    }

    public function test_the_allow_list_has_no_stale_entries(): void
    {
        $existing = array_map('basename', array_keys($this->processorFiles()));

        foreach (array_keys(self::ALLOWED_UNGUARDED) as $allowed) {
            self::assertContains($allowed, $existing, sprintf(
                '%s is allow-listed but no longer exists. Remove the entry.',
                $allowed,
            ));
        }
    }

    /**
     * Guards against the glob silently matching nothing, which would make the test above pass vacuously.
     */
    public function test_the_scan_actually_finds_the_processors(): void
    {
        self::assertGreaterThan(70, count($this->processorFiles()));
    }

    /**
     * @return array<string, string> relative path => file contents
     */
    private function processorFiles(): array
    {
        // tests/Unit/Security/BandSpace -> project root
        $directory = \dirname(__DIR__, 4) . '/' . self::PROCESSOR_DIR;
        self::assertDirectoryExists($directory);

        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $source = file_get_contents($file->getPathname());
            self::assertIsString($source);
            // Keyed by sub-path, not basename: two processors with the same name in different
            // subdirectories would otherwise overwrite each other and drop out of the scan unnoticed.
            $files[self::PROCESSOR_DIR . '/' . $iterator->getSubPathname()] = $source;
        }

        ksort($files);

        return $files;
    }
}
