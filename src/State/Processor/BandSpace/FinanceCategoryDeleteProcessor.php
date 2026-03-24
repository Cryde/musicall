<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Finance\FinanceCategoryResource;
use App\Entity\User;
use App\Repository\BandSpace\FinanceCategoryRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<FinanceCategoryResource, void>
 */
readonly class FinanceCategoryDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private FinanceCategoryRepository $financeCategoryRepository,
        private Security $security,
    ) {
    }

    /**
     * @param FinanceCategoryResource $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        /** @var User $user */
        $user = $this->security->getUser();

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $category = $this->financeCategoryRepository->findOneByIdAndBandSpace($data->id, $bandSpace);
        if (!$category) {
            throw new NotFoundHttpException('Catégorie introuvable');
        }

        $this->entityManager->remove($category);
        $this->entityManager->flush();
    }
}
