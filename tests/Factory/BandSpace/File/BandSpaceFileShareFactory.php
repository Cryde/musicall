<?php declare(strict_types=1);

namespace App\Tests\Factory\BandSpace\File;

use App\Entity\BandSpace\BandSpaceFileShare;
use App\Tests\Factory\User\UserFactory;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;

/**
 * @extends PersistentProxyObjectFactory<BandSpaceFileShare>
 */
final class BandSpaceFileShareFactory extends PersistentProxyObjectFactory
{
    protected function defaults(): array
    {
        return [
            'bandSpaceFile' => BandSpaceFileFactory::new(),
            'createdBy' => UserFactory::new(),
            'tokenHash' => hash('sha256', bin2hex(random_bytes(32))),
            'creationDatetime' => self::faker()->dateTime(),
        ];
    }

    public static function class(): string
    {
        return BandSpaceFileShare::class;
    }

    /**
     * Persists a share whose tokenHash is the SHA-256 of the supplied clear text.
     * The caller keeps the clear text locally to use it in assertions/requests.
     *
     * @param array<string, mixed> $attributes
     *
     * @return BandSpaceFileShare&Proxy<BandSpaceFileShare>
     */
    public static function createOneWithToken(string $clearToken, array $attributes = []): mixed
    {
        return self::createOne([
            ...$attributes,
            'tokenHash' => hash('sha256', $clearToken),
        ]);
    }
}
