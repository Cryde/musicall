<?php declare(strict_types=1);

namespace App\Service\BandSpace;

use App\Enum\BandSpace\BandSpaceModule;
use DateTimeImmutable;

final readonly class BandSpaceActivityFilter
{
    public const int DEFAULT_LIMIT = 50;
    public const int MAX_LIMIT = 200;

    /**
     * @param BandSpaceModule[] $modules empty = all modules
     */
    public function __construct(
        public array $modules = [],
        public ?string $actorId = null,
        public ?string $type = null,
        public ?DateTimeImmutable $from = null,
        public ?DateTimeImmutable $to = null,
        public int $limit = self::DEFAULT_LIMIT,
        public int $offset = 0,
    ) {
    }
}
