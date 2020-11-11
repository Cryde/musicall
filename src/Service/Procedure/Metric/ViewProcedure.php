<?php

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
    const DATE_VALIDITY_PERIOD = '24 hour ago';

    private ViewCacheDirector $viewCacheDirector;
    private EntityManagerInterface $entityManager;
    private ViewRepository $viewRepository;
    private RequestIdentifier $requestIdentifier;
    private ViewDirector $viewDirector;

    public function __construct(
        EntityManagerInterface $entityManager,
        ViewCacheDirector $viewCacheDirector,
        ViewRepository $viewRepository,
        RequestIdentifier $requestIdentifier,
        ViewDirector $viewDirector
    ) {
        $this->viewCacheDirector = $viewCacheDirector;
        $this->entityManager = $entityManager;
        $this->viewRepository = $viewRepository;
        $this->requestIdentifier = $requestIdentifier;
        $this->viewDirector = $viewDirector;
    }

    public function process(ViewableInterface $viewable, Request $request, ?User $user = null)
    {
        $viewCache = $viewable->getViewCache();
        if (!$viewable->getViewCache()) {
            $viewCache = $this->viewCacheDirector->build();
            $viewable->setViewCache($viewCache);
            $this->entityManager->persist($viewCache);
            $this->entityManager->flush();
        }

        $view = null;
        if ($user) {
            $view = $this->viewRepository->findOneByUser($viewCache, $user);
        } else {
            $datetime = new \DateTime(self::DATE_VALIDITY_PERIOD);
            $view = $this->viewRepository->findOneByIdentifierAndPeriod($viewCache, $this->requestIdentifier->fromRequest($request), $datetime);
        }

        if (!$view) {
            $view = $this->viewDirector->build($viewCache, $this->requestIdentifier->fromRequest($request), $user);
            $this->entityManager->persist($view);
            $this->entityManager->refresh($viewCache);
            $viewCache->setCount($viewCache->getCount() + 1);
            $this->entityManager->flush();
        }
    }
}
