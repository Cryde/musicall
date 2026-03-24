<?php declare(strict_types=1);

namespace App\State\Provider\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\BandSpace\Finance\FinanceCategoryResource;
use App\Entity\User;
use App\Repository\BandSpace\FinanceCategoryRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\Builder\BandSpace\FinanceCategoryBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<FinanceCategoryResource>
 */
readonly class FinanceCategoryItemProvider implements ProviderInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private FinanceCategoryRepository $financeCategoryRepository,
        private FinanceCategoryBuilder $financeCategoryBuilder,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): FinanceCategoryResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $category = $this->financeCategoryRepository->findOneByIdAndBandSpace((string) $uriVariables['id'], $bandSpace);
        if (!$category) {
            throw new NotFoundHttpException('Catégorie introuvable');
        }

        return $this->financeCategoryBuilder->buildItem($category);
    }
}
