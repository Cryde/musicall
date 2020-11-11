<?php

namespace App\Contracts\Metric;

use App\Entity\Metric\ViewCache;

interface ViewableInterface
{
    public function getViewCache(): ?ViewCache;

    public function setViewCache(?ViewCache $viewCache): self;
}
