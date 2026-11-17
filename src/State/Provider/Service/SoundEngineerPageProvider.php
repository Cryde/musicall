<?php

declare(strict_types=1);

namespace App\State\Provider\Service;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Service\SoundEngineering\SoundEngineerPage;
use App\Enum\Service\ServiceKind;
use App\Security\Service\ServiceVisibilityChecker;
use App\Service\Builder\Service\SoundEngineerPageBuilder;
use App\Service\Profile\ProfileViewTracker;

/**
 * @implements ProviderInterface<SoundEngineerPage>
 */
readonly class SoundEngineerPageProvider implements ProviderInterface
{
    public function __construct(
        private ServiceVisibilityChecker $visibilityChecker,
        private ProfileViewTracker $viewTracker,
        private SoundEngineerPageBuilder $builder,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): SoundEngineerPage
    {
        $service = $this->visibilityChecker->checkVisibleByUsername((string) ($uriVariables['username'] ?? ''), ServiceKind::SoundEngineering);
        $this->viewTracker->track($service, $service->user);

        return $this->builder->build($service);
    }
}
