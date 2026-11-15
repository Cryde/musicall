<?php declare(strict_types=1);

namespace App\Tests\Unit\Service\File;

use App\Http\ExecutionTimeLimit;
use App\Service\File\TransferTimeLimitedStorage;
use PHPUnit\Framework\TestCase;
use Vich\UploaderBundle\Mapping\PropertyMappingInterface;
use Vich\UploaderBundle\Storage\StorageInterface;

class TransferTimeLimitedStorageTest extends TestCase
{
    public function test_upload_extends_the_time_limit_before_writing(): void
    {
        $calls = [];
        $uploadable = new \stdClass();
        $mapping = $this->createStub(PropertyMappingInterface::class);

        $inner = $this->createMock(StorageInterface::class);
        $inner->expects(self::once())
            ->method('upload')
            ->with($uploadable, $mapping)
            ->willReturnCallback(static function () use (&$calls): void {
                $calls[] = 'upload';
            });

        (new TransferTimeLimitedStorage($inner, $this->recordingTimeLimit($calls)))->upload($uploadable, $mapping);

        self::assertSame(['extend', 'upload'], $calls);
    }

    public function test_resolve_stream_extends_the_time_limit_before_opening_the_stream(): void
    {
        $calls = [];
        $uploadable = new \stdClass();
        $stream = fopen('php://memory', 'rb');

        $inner = $this->createMock(StorageInterface::class);
        $inner->expects(self::once())
            ->method('resolveStream')
            ->with($uploadable, 'uploadedFile', null)
            ->willReturnCallback(static function () use (&$calls, $stream) {
                $calls[] = 'resolveStream';

                return $stream;
            });

        $resolved = (new TransferTimeLimitedStorage($inner, $this->recordingTimeLimit($calls)))->resolveStream($uploadable, 'uploadedFile');

        self::assertSame($stream, $resolved);
        self::assertSame(['extend', 'resolveStream'], $calls);
    }

    public function test_the_other_operations_delegate_without_touching_the_time_limit(): void
    {
        $uploadable = new \stdClass();
        $mapping = $this->createStub(PropertyMappingInterface::class);

        $executionTimeLimit = $this->createMock(ExecutionTimeLimit::class);
        $executionTimeLimit->expects(self::never())->method('extendForTransfer');
        $inner = $this->createMock(StorageInterface::class);
        $inner->expects(self::once())->method('remove')->with($uploadable, $mapping, 'dir')->willReturn(true);
        $inner->expects(self::once())->method('resolvePath')->with($uploadable, 'uploadedFile', null, true)->willReturn('/a/b.pdf');
        $inner->expects(self::once())->method('resolveUri')->with($uploadable, 'uploadedFile', null)->willReturn('/b.pdf');
        $inner->expects(self::once())->method('listFiles')->with($mapping)->willReturn([]);

        $storage = new TransferTimeLimitedStorage($inner, $executionTimeLimit);

        self::assertTrue($storage->remove($uploadable, $mapping, 'dir'));
        self::assertSame('/a/b.pdf', $storage->resolvePath($uploadable, 'uploadedFile', null, true));
        self::assertSame('/b.pdf', $storage->resolveUri($uploadable, 'uploadedFile'));
        self::assertSame([], $storage->listFiles($mapping));
    }

    /**
     * @param list<string> $calls
     */
    private function recordingTimeLimit(array &$calls): ExecutionTimeLimit
    {
        $executionTimeLimit = $this->createMock(ExecutionTimeLimit::class);
        $executionTimeLimit->expects(self::once())
            ->method('extendForTransfer')
            ->willReturnCallback(static function () use (&$calls): void {
                $calls[] = 'extend';
            });

        return $executionTimeLimit;
    }
}
