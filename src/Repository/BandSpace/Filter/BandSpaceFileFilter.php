<?php declare(strict_types=1);

namespace App\Repository\BandSpace\Filter;

final readonly class BandSpaceFileFilter
{
    public const int DEFAULT_LIMIT = 50;
    public const int MAX_LIMIT = 200;

    public function __construct(
        public ?string $folderId = null,
        public ?string $tagId = null,
        public ?string $source = null,
        public ?string $query = null,
        public ?string $mime = null,
        public ?string $uploaderId = null,
        public string $sort = 'date',
        public string $order = 'desc',
        public int $limit = self::DEFAULT_LIMIT,
        public int $offset = 0,
    ) {
    }
}
