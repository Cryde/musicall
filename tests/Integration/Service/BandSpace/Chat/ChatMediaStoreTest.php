<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service\BandSpace\Chat;

use App\Service\BandSpace\Chat\ChatMediaStore;
use App\Service\Procedure\Message\MessageSenderProcedure;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\BandSpace\BandSpaceMembershipFactory;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * The media of a band chat message that could not be sent (#1255): the send fails inside its
 * transaction, which closes the entity manager, and the media stored before it must still go.
 */
#[ResetDatabase]
class ChatMediaStoreTest extends KernelTestCase
{
    private ?string $temporaryFile = null;

    protected function tearDown(): void
    {
        if ($this->temporaryFile !== null && is_file($this->temporaryFile)) {
            unlink($this->temporaryFile);
        }

        parent::tearDown();
    }

    public function test_media_of_a_failed_send_is_discarded_once_the_entity_manager_is_closed(): void
    {
        self::bootKernel();
        $member = UserFactory::new()->asBaseUser()->create(['username' => 'batteur', 'email' => 'batteur@test.com']);
        $space = BandSpaceFactory::new()->create();
        BandSpaceMembershipFactory::new(['bandSpace' => $space, 'user' => $member])->create();
        $channel = MessageThreadFactory::new()->forBandSpace($space)->create();

        $store = self::getContainer()->get(ChatMediaStore::class);
        $file = $store->storeImage($this->jpegUpload(), $space, $member);
        $fileId = (string) $file->id;
        $path = '/band_space_files/' . $space->id . '/' . $file->currentVersion?->storagePath;
        /** @var FilesystemOperator $filesystem */
        $filesystem = self::getContainer()->get('oneup_flysystem.musicall_filesystem');
        $this->assertTrue($filesystem->fileExists($path));

        try {
            self::getContainer()->get(MessageSenderProcedure::class)->processByThread(
                $channel,
                $member,
                '',
                static function (): void {
                    throw new \RuntimeException('the send fails');
                },
            );
            $this->fail('The send was expected to fail');
        } catch (\RuntimeException) {
        }
        $this->assertFalse(self::getContainer()->get(EntityManagerInterface::class)->isOpen());

        $store->discardUnsent($file);

        $this->assertFalse($filesystem->fileExists($path));
        $connection = self::getContainer()->get(Connection::class);
        $this->assertFalse($connection->fetchOne('SELECT 1 FROM band_space_file WHERE id = ?', [$fileId]));
        $this->assertFalse($connection->fetchOne('SELECT 1 FROM band_space_file_version WHERE band_space_file_id = ?', [$fileId]));
    }

    private function jpegUpload(): UploadedFile
    {
        $this->temporaryFile = (string) tempnam(sys_get_temp_dir(), 'chat_media_store_test_');
        imagejpeg(imagecreatetruecolor(400, 300), $this->temporaryFile);

        return new UploadedFile($this->temporaryFile, 'photo.jpg', 'image/jpeg', null, true);
    }
}
