<?php

declare(strict_types=1);

namespace App\State\Provider\Service;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Service\Production\MyProduction;
use App\Entity\User;
use App\Enum\Service\ServiceKind;
use App\Security\Service\ServiceOwnerChecker;
use App\Service\Builder\Service\MyServiceFormBuilder;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * A production service's form (#1413), read before its PATCH merges the body in (#1327).
 *
 * @implements ProviderInterface<MyProduction>
 */
readonly class MyProductionProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private ServiceOwnerChecker $ownerChecker,
        private MyServiceFormBuilder $formBuilder,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): MyProduction
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $service = $this->ownerChecker->checkOwnerOfKind((string) $uriVariables['id'], $user, ServiceKind::Production);

        $form = new MyProduction();
        $this->formBuilder->fill($form, $service);

        return $form;
    }
}
