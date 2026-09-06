<?php

namespace App\Tests\Factory\BandSpace;

use App\Entity\BandSpace\AgendaFeedToken;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<AgendaFeedToken>
 */
final class AgendaFeedTokenFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'membership' => BandSpaceMembershipFactory::new(),
            'tokenHash' => hash('sha256', self::faker()->uuid()),
            'creationDatetime' => new \DateTimeImmutable(),
        ];
    }

    /**
     * Seeds the row a plaintext token unlocks, so a test can call the public feed without first
     * going through the authenticated generate endpoint. Only the hash is ever stored, which is why
     * the plaintext has to be chosen by the caller rather than read back.
     */
    public function withPlainToken(string $token): static
    {
        return $this->with(['tokenHash' => hash('sha256', $token)]);
    }

    public static function class(): string
    {
        return AgendaFeedToken::class;
    }
}
