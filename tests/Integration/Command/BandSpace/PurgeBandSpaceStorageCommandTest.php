<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command\BandSpace;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceFile;
use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileVersionFactory;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToDeleteDirectory;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * The point of #748: a Band Space deletion must actually reach the object storage. These tests assert on
 * the filesystem, not only on the rows, because the rows were already going away by FK cascade while the
 * objects stayed behind forever.
 */
#[ResetDatabase]
class PurgeBandSpaceStorageCommandTest extends KernelTestCase
{
    private const string STORAGE_PREFIX = 'band_space_files';

    private ?CommandTester $tester = null;

    private ?FilesystemOperator $realFilesystem = null;

    protected function setUp(): void
    {
        self::bootKernel();
        parent::setUp();
    }

    /**
     * Both the command and the filesystem are resolved on first use rather than in setUp: the container
     * refuses to replace an already-initialised service, and the failure test has to swap the filesystem
     * out before anything touches it.
     */
    private function filesystem(): FilesystemOperator
    {
        if (!$this->realFilesystem instanceof FilesystemOperator) {
            /** @var FilesystemOperator $filesystem */
            $filesystem = self::getContainer()->get('oneup_flysystem.musicall_filesystem');
            $this->realFilesystem = $filesystem;
        }

        return $this->realFilesystem;
    }

    private function commandTester(): CommandTester
    {
        if (!$this->tester instanceof CommandTester) {
            $application = new Application(self::$kernel);
            $this->tester = new CommandTester($application->find('app:band-space:purge'));
        }

        return $this->tester;
    }

    public function test_it_deletes_archived_files_and_their_objects_past_the_cutoff(): void
    {
        $bandSpace = BandSpaceFactory::new()->create();

        [$oldFile, $oldPath] = $this->createFileWithObject($bandSpace, new DateTimeImmutable('-40 days'));
        [$recentFile, $recentPath] = $this->createFileWithObject($bandSpace, new DateTimeImmutable('-5 days'));
        [$liveFile, $livePath] = $this->createFileWithObject($bandSpace, null);

        $oldFileId = (string) $oldFile->id;
        $recentFileId = (string) $recentFile->id;
        $liveFileId = (string) $liveFile->id;

        $this->commandTester()->execute([]);
        $this->commandTester()->assertCommandIsSuccessful();

        // Archived long enough ago: object and rows are gone.
        $this->assertFalse($this->filesystem()->fileExists($oldPath));
        $this->assertNull($this->findFile($oldFileId));

        // Archived recently: still inside the 30-day window.
        $this->assertTrue($this->filesystem()->fileExists($recentPath));
        $this->assertNotNull($this->findFile($recentFileId));

        // Never archived: untouched.
        $this->assertTrue($this->filesystem()->fileExists($livePath));
        $this->assertNotNull($this->findFile($liveFileId));
    }

    public function test_it_deletes_a_band_space_past_its_grace_period_with_all_its_objects(): void
    {
        $dueSpace = BandSpaceFactory::new()->create([
            'deletionScheduledDatetime' => new DateTimeImmutable('-1 day'),
        ]);
        $pendingSpace = BandSpaceFactory::new()->create([
            'deletionScheduledDatetime' => new DateTimeImmutable('+10 days'),
        ]);
        $keptSpace = BandSpaceFactory::new()->create();

        // A live file in the doomed space: the purge must not care whether it was archived.
        [, $duePath] = $this->createFileWithObject($dueSpace, null);
        [, $pendingPath] = $this->createFileWithObject($pendingSpace, null);
        [, $keptPath] = $this->createFileWithObject($keptSpace, null);

        $dueSpaceId = (string) $dueSpace->id;
        $pendingSpaceId = (string) $pendingSpace->id;
        $keptSpaceId = (string) $keptSpace->id;

        $this->commandTester()->execute([]);
        $this->commandTester()->assertCommandIsSuccessful();

        $this->assertSame(0, $this->countBandSpaceRows($dueSpaceId));
        $this->assertFalse($this->filesystem()->fileExists($duePath));
        $this->assertFalse($this->filesystem()->directoryExists(self::STORAGE_PREFIX . '/' . $dueSpaceId));

        // The space is removed with a bulk DQL delete, which performs no ORM cascade: the children only
        // disappear because every child table declares ON DELETE CASCADE on band_space. Asserted here so
        // that guarantee cannot be lost silently by a mapping change.
        $this->assertSame(0, $this->countRows('band_space_file', 'band_space_id', $dueSpaceId));
        $this->assertSame(0, $this->countRows('band_space_membership', 'band_space_id', $dueSpaceId));

        $this->assertSame(1, $this->countBandSpaceRows($pendingSpaceId));
        $this->assertTrue($this->filesystem()->fileExists($pendingPath));

        $this->assertSame(1, $this->countBandSpaceRows($keptSpaceId));
        $this->assertTrue($this->filesystem()->fileExists($keptPath));
    }

    public function test_dry_run_changes_nothing(): void
    {
        $bandSpace = BandSpaceFactory::new()->create([
            'deletionScheduledDatetime' => new DateTimeImmutable('-1 day'),
        ]);
        [$file, $path] = $this->createFileWithObject($bandSpace, new DateTimeImmutable('-40 days'));

        $bandSpaceId = (string) $bandSpace->id;
        $fileId = (string) $file->id;

        $this->commandTester()->execute(['--dry-run' => true]);
        $this->commandTester()->assertCommandIsSuccessful();

        $this->assertTrue($this->filesystem()->fileExists($path));
        $this->assertNotNull($this->findFile($fileId));
        $this->assertSame(1, $this->countBandSpaceRows($bandSpaceId));

        $output = $this->commandTester()->getDisplay();
        $this->assertStringContainsString('Dry run', $output);
        $this->assertStringContainsString('would delete file ' . $fileId, $output);
        $this->assertStringContainsString('would delete band space ' . $bandSpaceId, $output);
    }

    public function test_days_option_narrows_the_cutoff(): void
    {
        $bandSpace = BandSpaceFactory::new()->create();
        [$file, $path] = $this->createFileWithObject($bandSpace, new DateTimeImmutable('-5 days'));

        $fileId = (string) $file->id;

        // Default 30 days would keep it; --days=1 makes it due.
        $this->commandTester()->execute(['--days' => '1']);
        $this->commandTester()->assertCommandIsSuccessful();

        $this->assertFalse($this->filesystem()->fileExists($path));
        $this->assertNull($this->findFile($fileId));
    }

    public function test_negative_days_is_rejected(): void
    {
        $exitCode = $this->commandTester()->execute(['--days' => '-1']);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('cannot be negative', $this->commandTester()->getDisplay());
    }

    /**
     * Locks in the command's core safety property: storage is deleted before the rows, so a storage
     * failure must leave the row in place for the next run to retry. Deleting the row first would lose
     * the only pointer to the object and orphan it for good.
     */
    public function test_a_storage_failure_keeps_the_row_and_does_not_affect_the_other_spaces(): void
    {
        $failingSpace = BandSpaceFactory::new()->create([
            'deletionScheduledDatetime' => new DateTimeImmutable('-2 days'),
        ]);
        $healthySpace = BandSpaceFactory::new()->create([
            'deletionScheduledDatetime' => new DateTimeImmutable('-1 day'),
        ]);

        $failingSpaceId = (string) $failingSpace->id;
        $healthySpaceId = (string) $healthySpace->id;

        // Swapped in before anything resolves the real service. Only deleteDirectory is stubbed: it is the
        // single filesystem call the space purge makes, and no file is seeded here, so the archived-file
        // phase never needs storage either.
        $failingFilesystem = $this->createStub(FilesystemOperator::class);
        $failingFilesystem
            ->method('deleteDirectory')
            ->willReturnCallback(static function (string $location) use ($failingSpaceId): void {
                if (str_contains($location, $failingSpaceId)) {
                    throw UnableToDeleteDirectory::atLocation($location, 'storage unavailable');
                }
            });
        self::getContainer()->set('oneup_flysystem.musicall_filesystem', $failingFilesystem);

        $exitCode = $this->commandTester()->execute([]);

        // Reported as a failure so the cron alerts, but the healthy space is still purged.
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Failed to purge band space ' . $failingSpaceId, $this->commandTester()->getDisplay());

        // The row survived, so the next run retries and the object is never orphaned.
        $this->assertSame(1, $this->countBandSpaceRows($failingSpaceId));
        $this->assertSame(0, $this->countBandSpaceRows($healthySpaceId));
    }

    /**
     * Both phases in one run, against the same space: the archived file is purged first, then the space
     * itself. Makes the phase ordering explicit so a future refactor cannot silently invert it.
     */
    public function test_it_purges_an_archived_file_and_its_space_in_the_same_run(): void
    {
        $bandSpace = BandSpaceFactory::new()->create([
            'deletionScheduledDatetime' => new DateTimeImmutable('-1 day'),
        ]);

        [$archivedFile, $archivedPath] = $this->createFileWithObject($bandSpace, new DateTimeImmutable('-40 days'));
        [, $livePath] = $this->createFileWithObject($bandSpace, null);

        $bandSpaceId = (string) $bandSpace->id;
        $archivedFileId = (string) $archivedFile->id;

        $this->commandTester()->execute([]);
        $this->commandTester()->assertCommandIsSuccessful();

        $this->assertNull($this->findFile($archivedFileId));
        $this->assertSame(0, $this->countBandSpaceRows($bandSpaceId));
        $this->assertFalse($this->filesystem()->fileExists($archivedPath));
        $this->assertFalse($this->filesystem()->fileExists($livePath));
        $this->assertFalse($this->filesystem()->directoryExists(self::STORAGE_PREFIX . '/' . $bandSpaceId));
    }

    /**
     * @return array{BandSpaceFile, string} the file and the storage path of its only version
     */
    private function createFileWithObject(BandSpace $bandSpace, ?DateTimeImmutable $archivedAt): array
    {
        $file = BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'archiveDatetime' => $archivedAt,
        ])->create();

        $storagePath = bin2hex(random_bytes(8)) . '.pdf';
        $version = BandSpaceFileVersionFactory::new([
            'bandSpaceFile' => $file,
            'versionNumber' => 1,
            'storagePath' => $storagePath,
        ])->create();

        $file->currentVersion = $version;

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->flush();

        $path = self::STORAGE_PREFIX . '/' . $bandSpace->id . '/' . $storagePath;
        $this->filesystem()->write($path, 'purge-test');

        return [$file, $path];
    }

    private function findFile(string $id): ?BandSpaceFile
    {
        return self::getContainer()->get(BandSpaceFileRepository::class)->find($id);
    }

    /**
     * Queried through DBAL on purpose: the command removes a space with a bulk DQL delete (the FK cascade
     * does the rest), which never touches Doctrine's identity map, so find() would happily return an
     * entity whose row is already gone.
     */
    private function countBandSpaceRows(string $id): int
    {
        return $this->countRows('band_space', 'id', $id);
    }

    private function countRows(string $table, string $column, string $value): int
    {
        return (int) self::getContainer()->get(Connection::class)->fetchOne(
            sprintf('SELECT COUNT(*) FROM %s WHERE %s = :value', $table, $column),
            ['value' => $value],
        );
    }
}
