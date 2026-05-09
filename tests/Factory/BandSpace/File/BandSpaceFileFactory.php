<?php declare(strict_types=1);

namespace App\Tests\Factory\BandSpace\File;

use App\Entity\BandSpace\BandSpaceFile;
use App\Tests\Factory\BandSpace\BandSpaceFactory;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<BandSpaceFile>
 */
final class BandSpaceFileFactory extends PersistentProxyObjectFactory
{
    protected function defaults(): array
    {
        return [
            'bandSpace' => BandSpaceFactory::new(),
            'createdBy' => UserFactory::new(),
            'originalName' => self::faker()->word() . '.pdf',
            'creationDatetime' => self::faker()->dateTime(),
        ];
    }

    /**
     * @param array{type: string, id: string|\Ramsey\Uuid\UuidInterface, attachedBy?: \App\Entity\User|\Zenstruck\Foundry\Persistence\Proxy<\App\Entity\User>} $source
     */
    public function withAttachment(array $source): self
    {
        return $this->afterPersist(function (\App\Entity\BandSpace\BandSpaceFile $file) use ($source): void {
            BandSpaceFileAttachmentFactory::createOne([
                'bandSpaceFile' => $file,
                'sourceType' => $source['type'],
                'sourceId' => $source['id'],
                'attachedBy' => $source['attachedBy'] ?? $file->createdBy,
            ]);
        });
    }

    public static function class(): string
    {
        return BandSpaceFile::class;
    }
}
