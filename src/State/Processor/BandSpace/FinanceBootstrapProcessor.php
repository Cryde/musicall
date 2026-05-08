<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Finance\FinanceBootstrapRequest;
use App\ApiResource\BandSpace\Finance\FinanceCategoryResource;
use App\Entity\BandSpace\FinanceCategory;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceFinanceActivityType;
use App\Enum\BandSpace\BandSpaceModule;
use App\Repository\BandSpace\FinanceCategoryRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\BandSpace\BandSpaceActivityRecorder;
use App\Service\Builder\BandSpace\FinanceCategoryBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProcessorInterface<FinanceBootstrapRequest, FinanceCategoryResource[]>
 */
readonly class FinanceBootstrapProcessor implements ProcessorInterface
{
    private const array DEFAULT_POLES = [
        'Studio / Enregistrement',
        'Mix & Mastering',
        'Clips',
        'Communication & Promo',
        'Live & Concerts',
        'Matériel',
        'Identité visuelle',
        'Distribution & Admin',
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private FinanceCategoryRepository $financeCategoryRepository,
        private FinanceCategoryBuilder $financeCategoryBuilder,
        private BandSpaceActivityRecorder $bandSpaceActivityRecorder,
        private Security $security,
    ) {
    }

    /**
     * @param FinanceBootstrapRequest $data
     * @return FinanceCategoryResource[]
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): array
    {
        /** @var User $user */
        $user = $this->security->getUser();

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        if ($this->financeCategoryRepository->existsByBandSpace($bandSpace)) {
            $categories = $this->financeCategoryRepository->findByBandSpace($bandSpace);

            return $this->financeCategoryBuilder->buildFromList($categories);
        }

        $categories = [];
        foreach (self::DEFAULT_POLES as $position => $name) {
            $category = new FinanceCategory();
            $category->bandSpace = $bandSpace;
            $category->name = $name;
            $category->position = $position;

            $this->entityManager->persist($category);
            $categories[] = $category;
        }

        $this->entityManager->flush();

        $this->bandSpaceActivityRecorder->record(
            bandSpace: $bandSpace,
            module: BandSpaceModule::Finance,
            type: BandSpaceFinanceActivityType::CategoriesBootstrapped,
            actor: $user,
            payload: ['count' => count($categories)],
        );
        $this->entityManager->flush();

        return $this->financeCategoryBuilder->buildFromList($categories);
    }
}
