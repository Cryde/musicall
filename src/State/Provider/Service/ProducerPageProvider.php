<?php

declare(strict_types=1);

namespace App\State\Provider\Service;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Service\Production\ProducerPage;
use App\Enum\Service\ServiceKind;
use App\Security\Service\ServiceVisibilityChecker;
use App\Service\Builder\Service\ServicePageBuilder;
use App\Service\Profile\ProfileViewTracker;

/**
 * A producer's page (#1413): what every kind's page shows, with no details of its own.
 *
 * @implements ProviderInterface<ProducerPage>
 */
readonly class ProducerPageProvider implements ProviderInterface
{
    public function __construct(
        private ServiceVisibilityChecker $visibilityChecker,
        private ProfileViewTracker $viewTracker,
        private ServicePageBuilder $pageBuilder,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ProducerPage
    {
        $service = $this->visibilityChecker->checkVisibleByUsername((string) ($uriVariables['username'] ?? ''), ServiceKind::Production);
        $this->viewTracker->track($service, $service->user);

        $page = new ProducerPage();
        $this->pageBuilder->fill($page, $service);

        return $page;
    }
}
