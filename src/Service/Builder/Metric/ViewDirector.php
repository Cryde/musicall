<?php declare(strict_types=1);

namespace App\Service\Builder\Metric;

use App\Entity\Metric\View;
use App\Entity\Metric\ViewCache;
use App\Entity\User;

class ViewDirector
{
    public function build(ViewCache $viewCache, string $identifier, ?User $user): View
    {
        return (new View())
            ->setUser($user)
            ->setIdentifier($identifier)
            ->setViewCache($viewCache);
    }
}
