<?php

declare(strict_types=1);

namespace App\State\Provider\Service;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Service\Visual\VisualServicePage;
use App\Security\Service\ServiceVisibilityChecker;
use App\Service\Builder\Service\VisualServicePageBuilder;
use App\Service\Profile\ProfileViewTracker;

/**
 * A photographer's or a videographer's page (#1411), the kind named by the operation's resource.
 *
 * @implements ProviderInterface<VisualServicePage>
 */
readonly class VisualServicePageProvider implements ProviderInterface
{
    public function __construct(
        private ServiceVisibilityChecker $visibilityChecker,
        private ProfileViewTracker $viewTracker,
        private VisualServicePageBuilder $builder,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): VisualServicePage
    {
        /** @var class-string<VisualServicePage> $resourceClass */
        $resourceClass = $operation->getClass();
        $service = $this->visibilityChecker->checkVisibleByUsername((string) ($uriVariables['username'] ?? ''), $resourceClass::kind());
        $this->viewTracker->track($service, $service->user);

        return $this->builder->build($service, new $resourceClass());
    }
}
