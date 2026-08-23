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
        public ?string $sourceId = null,
        public ?string $query = null,
        public ?string $mime = null,
        public ?string $uploaderId = null,
        public string $sort = 'date',
        public string $order = 'desc',
        public int $limit = self::DEFAULT_LIMIT,
        public int $offset = 0,
        /**
         * Lists the trash instead of the live files: archived only, never both. Drives the ?archived=true
         * parameter of the file collection.
         */
        public bool $archivedOnly = false,
        /**
         * The root of the folder tree: files in no folder that are not an attachment either.
         *
         * Both halves are needed. An attachment is created with no folder unless one was passed, so
         * "no folder" alone would put every note, task and finance attachment in the root, where they
         * would sit alongside the virtual folder that already lists them.
         *
         * Distinct from a null `folderId`, which still means "every file in the space" and backs the
         * flat listing. Root is a place in the tree, no filter is the whole space.
         */
        public bool $rootOnly = false,
    ) {
    }
}
