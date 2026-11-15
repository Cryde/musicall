<?php declare(strict_types=1);

namespace App\Tests\Integration\Service\File;

use App\Http\ExecutionTimeLimit;
use App\Service\File\TransferTimeLimitedStorage;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\Service\ResetInterface;
use Vich\UploaderBundle\Storage\StorageInterface;

class TransferTimeLimitedStorageWiringTest extends KernelTestCase
{
    private int $limitBeforeTest;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->limitBeforeTest = (int) ini_get('max_execution_time');
    }

    protected function tearDown(): void
    {
        set_time_limit($this->limitBeforeTest);
        parent::tearDown();
    }

    public function test_vich_and_the_download_providers_get_the_decorator(): void
    {
        // Vich's upload handler is wired to this id, the download providers autowire the interface.
        self::assertInstanceOf(TransferTimeLimitedStorage::class, self::getContainer()->get('vich_uploader.storage'));
        self::assertInstanceOf(TransferTimeLimitedStorage::class, self::getContainer()->get(StorageInterface::class));
    }

    public function test_the_services_resetter_puts_the_limit_back_between_requests(): void
    {
        set_time_limit(30);
        $executionTimeLimit = self::getContainer()->get(ExecutionTimeLimit::class);
        self::assertInstanceOf(ExecutionTimeLimit::class, $executionTimeLimit);
        $executionTimeLimit->extendForTransfer();

        $servicesResetter = self::getContainer()->get('services_resetter');
        self::assertInstanceOf(ResetInterface::class, $servicesResetter);
        $servicesResetter->reset();

        self::assertSame('30', ini_get('max_execution_time'));
    }
}
