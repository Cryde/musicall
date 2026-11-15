<?php declare(strict_types=1);

namespace App\Service\File;

use App\Http\ExecutionTimeLimit;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Vich\UploaderBundle\Mapping\PropertyMappingInterface;
use Vich\UploaderBundle\Storage\StorageInterface;

/**
 * Every upload is written and every download streamed through Vich's storage, so one decorator gives
 * each file transfer its time limit, rather than each processor and provider (#1390).
 */
#[AsDecorator('vich_uploader.storage')]
final readonly class TransferTimeLimitedStorage implements StorageInterface
{
    public function __construct(
        #[AutowireDecorated]
        private StorageInterface $inner,
        private ExecutionTimeLimit $executionTimeLimit,
    ) {
    }

    public function upload(object $obj, PropertyMappingInterface $mapping): void
    {
        $this->executionTimeLimit->extendForTransfer();
        $this->inner->upload($obj, $mapping);
    }

    /**
     * Callers resolve the stream once access is checked, and the bytes are copied right after.
     *
     * @param object|array<string, mixed> $obj
     *
     * @return resource|null
     */
    public function resolveStream(object|array $obj, ?string $fieldName = null, ?string $className = null)
    {
        $this->executionTimeLimit->extendForTransfer();

        return $this->inner->resolveStream($obj, $fieldName, $className);
    }

    public function remove(object $obj, PropertyMappingInterface $mapping, ?string $dir = null): ?bool
    {
        return $this->inner->remove($obj, $mapping, $dir);
    }

    /**
     * @param object|array<string, mixed> $obj
     */
    public function resolvePath(object|array $obj, ?string $fieldName = null, ?string $className = null, ?bool $relative = false): ?string
    {
        return $this->inner->resolvePath($obj, $fieldName, $className, $relative);
    }

    /**
     * @param object|array<string, mixed> $obj
     */
    public function resolveUri(object|array $obj, ?string $fieldName = null, ?string $className = null): ?string
    {
        return $this->inner->resolveUri($obj, $fieldName, $className);
    }

    public function listFiles(PropertyMappingInterface $mapping): iterable
    {
        return $this->inner->listFiles($mapping);
    }
}
