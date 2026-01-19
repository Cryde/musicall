<?php declare(strict_types=1);

namespace App\Service\Procedure\Metric;

use App\Contracts\Metric\ViewableInterface;
use App\Entity\User;
use App\Repository\Metric\ViewRepository;
use App\Service\Builder\Metric\ViewCacheDirector;
use App\Service\Builder\Metric\ViewDirector;
use App\Service\Identifier\RequestIdentifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class ViewProcedure
{
    final const DATE_VALIDITY_PERIOD = '24 hour ago';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ViewCacheDirector      $viewCacheDirector,
        private readonly ViewRepository         $viewRepository,
        private readonly RequestIdentifier      $requestIdentifier,
        private readonly ViewDirector           $viewDirector
    ) {
    }

    public function process(ViewableInterface $viewable, Request $request, ?User $user = null): void
    {
        $viewCache = $viewable->getViewCache();
        if (!$viewCache) {
            $viewCache = $this->viewCacheDirector->build();
            $viewable->setViewCache($viewCache);
            $this->entityManager->persist($viewCache);
            $this->entityManager->flush();
        }

        $datetime = new \DateTime(self::DATE_VALIDITY_PERIOD);

        if ($user) {
            $view = $this->viewRepository->findOneByUserAndPeriod($viewCache, $user, $datetime);
        } else {
            $view = $this->viewRepository->findOneByIdentifierAndPeriod($viewCache, $this->requestIdentifier->fromRequest($request), $datetime);
        }

        if (!$view) {
            $view = $this->viewDirector->build(
                $viewCache,
                $this->requestIdentifier->fromRequest($request),
                $user,
                $viewable->getViewableType(),
                $viewable->getViewableId(),
            );
            $this->entityManager->persist($view);
            $this->entityManager->refresh($viewCache);
            $viewCache->setCount($viewCache->getCount() + 1);
            $this->entityManager->flush();
        }
    }
}
