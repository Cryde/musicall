<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service\Message;

use App\Service\Message\DirectMessageMediaStore;
use App\Service\Procedure\Message\MessageSenderProcedure;
use App\Tests\Factory\Message\MessageParticipantFactory;
use App\Tests\Factory\Message\MessageThreadFactory;
use App\Tests\Factory\User\UserFactory;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/**
 * The media of a direct message that could not be sent (#1247): the send fails inside its transaction,
 * which closes the entity manager, and the media stored before it must still go.
 */
#[ResetDatabase]
class DirectMessageMediaStoreTest extends KernelTestCase
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
        $sender = UserFactory::new()->asBaseUser()->create(['username' => 'alice_drums', 'email' => 'alice@test.com']);
        $other = UserFactory::new()->asBaseUser()->create(['username' => 'bob_bass', 'email' => 'bob@test.com']);
        $thread = MessageThreadFactory::new()->create();
        MessageParticipantFactory::new(['thread' => $thread, 'participant' => $sender])->create();
        MessageParticipantFactory::new(['thread' => $thread, 'participant' => $other])->create();

        $store = self::getContainer()->get(DirectMessageMediaStore::class);
        $file = $store->storeImage($this->jpegUpload(), $thread, $sender);
        $fileId = (string) $file->id;
        $path = '/message_files/' . $thread->id . '/' . $file->storagePath;
        /** @var FilesystemOperator $filesystem */
        $filesystem = self::getContainer()->get('oneup_flysystem.musicall_filesystem');
        $this->assertTrue($filesystem->fileExists($path));

        try {
            self::getContainer()->get(MessageSenderProcedure::class)->processByThread(
                $thread,
                $sender,
                '',
                static function (): void {
                    throw new \RuntimeException('the send fails');
                },
            );
            $this->fail('The send was expected to fail');
        } catch (\RuntimeException) {
        }
        $this->assertFalse(self::getContainer()->get(EntityManagerInterface::class)->isOpen());

        $store->discard($file);

        $this->assertFalse($filesystem->fileExists($path));
        $this->assertFalse(
            self::getContainer()->get(Connection::class)->fetchOne('SELECT 1 FROM message_file WHERE id = ?', [$fileId]),
        );
    }

    private function jpegUpload(): UploadedFile
    {
        $this->temporaryFile = (string) tempnam(sys_get_temp_dir(), 'direct_media_store_test_');
        imagejpeg(imagecreatetruecolor(400, 300), $this->temporaryFile);

        return new UploadedFile($this->temporaryFile, 'photo.jpg', 'image/jpeg', null, true);
    }
}
