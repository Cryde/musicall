<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Finance\FinanceEntryCreate;
use App\ApiResource\BandSpace\Finance\FinanceEntryResource;
use App\Entity\BandSpace\FinanceEntry;
use App\Entity\User;
use App\Enum\BandSpace\FinanceEntryScope;
use App\Enum\BandSpace\FinanceEntryStatus;
use App\Enum\BandSpace\FinanceEntryType;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Repository\BandSpace\FinanceCategoryRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\Builder\BandSpace\FinanceEntryBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<FinanceEntryCreate, FinanceEntryResource>
 */
readonly class FinanceEntryCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private FinanceCategoryRepository $financeCategoryRepository,
        private BandSpaceMembershipRepository $bandSpaceMembershipRepository,
        private FinanceEntryBuilder $financeEntryBuilder,
        private Security $security,
    ) {
    }

    /**
     * @param FinanceEntryCreate $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): FinanceEntryResource
    {
        /** @var User $user */
        $user = $this->security->getUser();

        [$bandSpace, $currentMembership] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $category = $this->financeCategoryRepository->findOneByIdAndBandSpace($data->categoryId, $bandSpace);
        if (!$category) {
            throw new NotFoundHttpException('Catégorie introuvable');
        }

        $entry = new FinanceEntry();
        $entry->category = $category;
        $entry->label = $data->label;
        $entry->type = FinanceEntryType::from($data->type);
        $entry->status = FinanceEntryStatus::from($data->status);
        $entry->amount = $data->amount;
        $entry->amountMin = $data->amountMin;
        $entry->amountMax = $data->amountMax;
        $entry->scope = FinanceEntryScope::from($data->scope);

        $entry->date = new \DateTime($data->date);

        if ($entry->scope === FinanceEntryScope::Personal) {
            $entry->member = $currentMembership;
        } elseif ($data->memberId !== null) {
            $member = $this->bandSpaceMembershipRepository->findOneByIdAndBandSpace($data->memberId, $bandSpace);
            if (!$member) {
                throw new NotFoundHttpException('Membre introuvable');
            }
            $entry->member = $member;
        }

        $this->entityManager->persist($entry);
        $this->entityManager->flush();

        return $this->financeEntryBuilder->buildItem($entry);
    }
}
