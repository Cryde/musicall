<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Finance\FinanceCategoryResource;
use App\Entity\User;
use App\Repository\BandSpace\FinanceCategoryRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\Builder\BandSpace\FinanceCategoryBuilder;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<FinanceCategoryResource, FinanceCategoryResource>
 */
readonly class FinanceCategoryUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BandSpaceMemberChecker $memberChecker,
        private FinanceCategoryRepository $financeCategoryRepository,
        private FinanceCategoryBuilder $financeCategoryBuilder,
        private Security $security,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param FinanceCategoryResource $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): FinanceCategoryResource
    {
        /** @var User $user */
        $user = $this->security->getUser();

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $category = $this->financeCategoryRepository->findOneByIdAndBandSpace($data->id, $bandSpace);
        if (!$category) {
            throw new NotFoundHttpException('Catégorie introuvable');
        }

        $requestPayload = $this->requestStack->getCurrentRequest()?->toArray() ?? [];

        if (array_key_exists('name', $requestPayload)) {
            $category->name = $data->name;
        }

        if (array_key_exists('position', $requestPayload)) {
            $category->position = $data->position;
        }

        if (array_key_exists('parent_id', $requestPayload)) {
            if ($data->parentId === null) {
                $category->parent = null;
            } else {
                $parent = $this->financeCategoryRepository->findOneByIdAndBandSpace($data->parentId, $bandSpace);
                if (!$parent) {
                    throw new NotFoundHttpException('Catégorie parente introuvable');
                }
                if ($parent->parent !== null) {
                    throw new BadRequestHttpException('La profondeur maximale de 2 niveaux est atteinte');
                }
                if ((string) $parent->id === (string) $category->id) {
                    throw new BadRequestHttpException('Une catégorie ne peut pas être son propre parent');
                }
                $category->parent = $parent;
            }
        }

        $category->updateDatetime = new DateTime();

        $this->entityManager->flush();

        return $this->financeCategoryBuilder->buildItem($category);
    }
}
