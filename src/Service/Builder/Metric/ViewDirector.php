<?php

namespace App\Service\Builder\Metric;

use App\Entity\Metric\View;
use App\Entity\Metric\ViewCache;
use App\Entity\User;

class ViewDirector
{
    public function build(ViewCache $viewCache, $identifier, ?User $user)
    {
        return (new View())
            ->setUser($user)
            ->setIdentifier($identifier)
            ->setViewCache($viewCache);
    }
}
