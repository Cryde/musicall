<?php declare(strict_types=1);

namespace App\Tests\Integration\Service\BandSpace\File;

use App\Repository\BandSpace\BandSpaceFileRepository;
use App\Service\BandSpace\File\BandSpaceFilePurger;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileFactory;
use App\Tests\Factory\BandSpace\File\BandSpaceFileVersionFactory;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * The purger is shared by app:band-space:purge and by the trash's delete-permanently endpoint, so its
 * two guarantees are tested here rather than twice over: it removes the stored objects, and it declines a
 * file that has left the trash since the caller loaded it.
 */
#[ResetDatabase]
class BandSpaceFilePurgerTest extends KernelTestCase
{
    private const string STORAGE_PREFIX = 'band_space_files';

    public function test_it_destroys_the_file_its_versions_and_every_stored_object(): void
    {
        [$file, $paths] = $this->createArchivedFileWithTwoVersions();
        $fileId = (string) $file->id;

        foreach ($paths as $path) {
            $this->assertTrue($this->filesystem()->fileExists($path));
        }

        $this->assertTrue($this->purger()->purge($file));

        $this->assertNull(self::getContainer()->get(BandSpaceFileRepository::class)->find($fileId));
        $this->assertSame(0, $this->countVersions($fileId));
        // Both versions, not just the current one: every version holds its own object.
        foreach ($paths as $path) {
            $this->assertFalse($this->filesystem()->fileExists($path), 'Every version object must be deleted');
        }
    }

    /**
     * The scenario this guards: app:band-space:purge reads its whole batch, then purges one file at a
     * time. A member restoring a file in between leaves the command holding an entity that still carries
     * the old archiveDatetime. Destroying it would silently lose the file someone just asked to keep.
     */
    public function test_it_declines_a_file_restored_since_the_caller_loaded_it(): void
    {
        [$file, $paths] = $this->createArchivedFileWithTwoVersions();
        $fileId = (string) $file->id;

        // Restore the row without touching the entity, which is what a concurrent request looks like.
        self::getContainer()->get(Connection::class)->executeStatement(
            'UPDATE band_space_file SET archive_datetime = NULL WHERE id = :id',
            ['id' => $fileId],
        );
        $this->assertNotNull($file->archiveDatetime, 'The entity in hand is deliberately stale');

        $this->assertFalse($this->purger()->purge($file));

        $this->assertNotNull(self::getContainer()->get(BandSpaceFileRepository::class)->find($fileId));
        $this->assertSame(2, $this->countVersions($fileId));
        foreach ($paths as $path) {
            $this->assertTrue($this->filesystem()->fileExists($path), 'A restored file keeps its objects');
        }
    }

    private function purger(): BandSpaceFilePurger
    {
        return self::getContainer()->get(BandSpaceFilePurger::class);
    }

    private function filesystem(): FilesystemOperator
    {
        /** @var FilesystemOperator $filesystem */
        $filesystem = self::getContainer()->get('oneup_flysystem.musicall_filesystem');

        return $filesystem;
    }

    /**
     * @return array{0: object, 1: string[]}
     */
    private function createArchivedFileWithTwoVersions(): array
    {
        $bandSpace = BandSpaceFactory::new()->create();
        $file = BandSpaceFileFactory::new([
            'bandSpace' => $bandSpace,
            'archiveDatetime' => new \DateTimeImmutable('-31 days'),
        ])->create();

        $paths = [];
        $versions = [];
        foreach ([1, 2] as $number) {
            $storagePath = 'purger-v' . $number . '-' . bin2hex(random_bytes(4)) . '.txt';
            $versions[] = BandSpaceFileVersionFactory::new([
                'bandSpaceFile' => $file,
                'versionNumber' => $number,
                'storagePath' => $storagePath,
            ])->create();
            $paths[] = self::STORAGE_PREFIX . '/' . $bandSpace->id . '/' . $storagePath;
        }

        $file->currentVersion = $versions[1];
        self::getContainer()->get(EntityManagerInterface::class)->flush();

        foreach ($paths as $path) {
            $this->filesystem()->write($path, 'purger-test');
        }

        return [$file, $paths];
    }

    private function countVersions(string $fileId): int
    {
        return (int) self::getContainer()->get(Connection::class)->fetchOne(
            'SELECT COUNT(*) FROM band_space_file_version WHERE band_space_file_id = :id',
            ['id' => $fileId],
        );
    }
}
