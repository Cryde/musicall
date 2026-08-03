<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\TechRider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\TechRider\TechRiderDuplicate;
use App\ApiResource\BandSpace\TechRider\TechRiderResource;
use App\Entity\BandSpace\TechRider;
use App\Entity\User;
use App\Procedure\BandSpace\TechRiderDuplicateProcedure;
use App\Repository\BandSpace\TechRiderRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use App\Service\Builder\BandSpace\TechRider\TechRiderBuilder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<TechRiderDuplicate, TechRiderResource>
 */
readonly class TechRiderDuplicateProcessor implements ProcessorInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private TechRiderRepository $techRiderRepository,
        private TechRiderDuplicateProcedure $duplicateProcedure,
        private TechRiderBuilder $techRiderBuilder,
        private Security $security,
    ) {
    }

    /**
     * @param TechRiderDuplicate $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TechRiderResource
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMemberForWrite((string) $uriVariables['bandSpaceId'], $user);

        // Deliberately not guarded by TechRiderWriteGuard: an archived rider is a valid source, and
        // duplicating it does not modify it. Starting next year's rider from last year's archived
        // one is the reason this endpoint exists.
        $source = $this->techRiderRepository->findOneByIdAndBandSpace((string) $uriVariables['id'], $bandSpace);
        if (!$source instanceof TechRider) {
            throw new NotFoundHttpException('Tech rider introuvable');
        }

        // Null means "not provided, use the default". Validation has already refused blank, so any
        // string arriving here is a real name; trimmed only to store it tidily.
        return $this->techRiderBuilder->buildItem(
            $this->duplicateProcedure->duplicate($source, $data->name === null ? null : trim($data->name), $user),
        );
    }
}
