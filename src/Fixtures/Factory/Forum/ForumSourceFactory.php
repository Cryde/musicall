<?php declare(strict_types=1);

namespace App\Fixtures\Factory\Forum;

use App\Entity\Forum\ForumSource;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @codeCoverageIgnore
 *
 * @extends PersistentObjectFactory<ForumSource>
 */
final class ForumSourceFactory extends PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'creationDatetime' => self::faker()->dateTime(),
            'description' => self::faker()->text(255),
            'slug' => self::faker()->slug(),
        ];
    }

    public function asRoot(): self
    {
        return $this->with(['description' => 'Forum principal', 'slug' => 'root']);
    }

    public static function class(): string
    {
        return ForumSource::class;
    }
}
