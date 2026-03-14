<?php declare(strict_types=1);

namespace App\Contracts\Metric;

use App\Entity\Metric\ViewCache;

interface ViewableInterface
{
    public ?ViewCache $viewCache { get; set; }

    public function getViewableId(): ?string;

    public function getViewableType(): string;
}
