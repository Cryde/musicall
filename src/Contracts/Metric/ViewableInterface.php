<?php declare(strict_types=1);

namespace App\Contracts\Metric;

use App\Entity\Metric\ViewCache;

interface ViewableInterface
{
    public function getViewCache(): ?ViewCache;

    public function setViewCache(?ViewCache $viewCache): self;
}
