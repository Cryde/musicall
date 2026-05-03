<?php declare(strict_types=1);

namespace App\Repository\BandSpace\Filter;

use DateTimeImmutable;

final readonly class TaskFilter
{
    public function __construct(
        public ?string $status = null,
        public ?string $categoryId = null,
        public ?string $assigneeId = null,
        public ?string $priority = null,
        public ?bool $archived = false,
        public ?string $query = null,
        public ?DateTimeImmutable $dueDateFrom = null,
        public ?DateTimeImmutable $dueDateTo = null,
        public bool $overdueOnly = false,
    ) {
    }
}
