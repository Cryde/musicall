<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\OAuth;

use App\Entity\Image\UserProfilePicture;
use App\Entity\User;
use App\Service\File\RemoteFileDownloader;
use App\Service\OAuth\ProfilePictureImporter;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class ProfilePictureImporterTest extends TestCase
{
    private RemoteFileDownloader $remoteFileDownloader;
    private EntityManagerInterface $entityManager;
    private ParameterBagInterface $parameterBag;
    private LoggerInterface $logger;
    private ProfilePictureImporter $importer;

    protected function setUp(): void
    {
        $this->remoteFileDownloader = $this->createMock(RemoteFileDownloader::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->parameterBag = $this->createMock(ParameterBagInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->importer = new ProfilePictureImporter(
            $this->remoteFileDownloader,
            $this->entityManager,
            $this->parameterBag,
            $this->logger,
        );
    }

    public function test_import_from_url_downloads_and_creates_profile_picture(): void
    {
        $user = new User();

        $this->parameterBag
            ->expects($this->once())
            ->method('get')
            ->with('file_user_profile_picture_destination')
            ->willReturn('/path/to/destination');

        $this->remoteFileDownloader
            ->expects($this->once())
            ->method('download')
            ->with('https://example.com/picture.jpg', '/path/to/destination')
            ->willReturn(['downloaded_picture.jpg', 12345]);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (UserProfilePicture $picture) use ($user) {
                return $picture->getImageName() === 'downloaded_picture.jpg'
                    && $picture->getImageSize() === 12345
                    && $picture->getUser() === $user
                    && $picture->getUpdatedAt() !== null;
            }));

        $this->logger
            ->expects($this->never())
            ->method('warning');

        $this->importer->importFromUrl($user, 'https://example.com/picture.jpg');

        $this->assertNotNull($user->getProfilePicture());
        $this->assertSame('downloaded_picture.jpg', $user->getProfilePicture()->getImageName());
        $this->assertSame(12345, $user->getProfilePicture()->getImageSize());
    }

    public function test_import_from_url_skips_when_user_already_has_profile_picture(): void
    {
        $existingPicture = new UserProfilePicture();
        $existingPicture->setImageName('existing.jpg');

        $user = new User();
        $user->setProfilePicture($existingPicture);

        $this->parameterBag
            ->expects($this->never())
            ->method('get');

        $this->remoteFileDownloader
            ->expects($this->never())
            ->method('download');

        $this->entityManager
            ->expects($this->never())
            ->method('persist');

        $this->importer->importFromUrl($user, 'https://example.com/picture.jpg');

        $this->assertSame($existingPicture, $user->getProfilePicture());
    }

    public function test_import_from_url_logs_warning_on_download_failure(): void
    {
        $user = new User()->setId('42');

        $this->parameterBag
            ->expects($this->once())
            ->method('get')
            ->with('file_user_profile_picture_destination')
            ->willReturn('/path/to/destination');

        $this->remoteFileDownloader
            ->expects($this->once())
            ->method('download')
            ->willThrowException(new \Exception('Download failed'));

        $this->entityManager
            ->expects($this->never())
            ->method('persist');

        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with(
                'Failed to import profile picture from OAuth provider',
                [
                    'userId' => '42',
                    'pictureUrl' => 'https://example.com/picture.jpg',
                    'error' => 'Download failed',
                ]
            );

        $this->importer->importFromUrl($user, 'https://example.com/picture.jpg');

        $this->assertNull($user->getProfilePicture());
    }
}
