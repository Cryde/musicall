<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service\Procedure\Profile;

use App\ApiResource\Profile\ProfilePostCreate;
use App\Repository\Profile\ProfilePostRepository;
use App\Service\Procedure\Profile\CreateProfilePostProcedure;
use App\Tests\Factory\User\UserFactory;
use League\Flysystem\FilesystemOperator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Zenstruck\Foundry\Attribute\ResetDatabase;

/** A post that cannot be stored takes back the photos it had already stored (#1316). */
#[ResetDatabase]
class CreateProfilePostProcedureTest extends KernelTestCase
{
    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        $this->temporaryFiles = [];

        parent::tearDown();
    }

    public function test_a_photo_that_cannot_be_converted_removes_the_ones_already_stored(): void
    {
        $author = UserFactory::new()->asBaseUser()->create();
        $input = new ProfilePostCreate();
        $input->content = 'Forest National';
        $input->photos = [$this->jpeg(120, 80, complete: true), $this->jpeg(120, 80, complete: false)];

        try {
            self::getContainer()->get(CreateProfilePostProcedure::class)->process($author, $input);
            $this->fail('A truncated photo cannot be converted.');
        } catch (\Throwable $exception) {
            $this->assertNotInstanceOf(\PHPUnit\Framework\AssertionFailedError::class, $exception);
        }

        /** @var FilesystemOperator $filesystem */
        $filesystem = self::getContainer()->get('oneup_flysystem.musicall_filesystem');
        $this->assertSame([], $filesystem->listContents('/profile_post_images/' . $author->id)->toArray());
        $this->assertSame([], self::getContainer()->get(ProfilePostRepository::class)->findAll());
    }

    /** A truncated JPEG keeps its header, so it reads as an image but cannot be decoded. */
    private function jpeg(int $width, int $height, bool $complete): UploadedFile
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'profile_post_procedure_test_');
        $this->temporaryFiles[] = $path;
        imagejpeg(imagecreatetruecolor($width, $height), $path);
        if (!$complete) {
            file_put_contents($path, substr((string) file_get_contents($path), 0, 200));
        }

        return new UploadedFile($path, 'photo.jpg', 'image/jpeg', null, true);
    }
}
