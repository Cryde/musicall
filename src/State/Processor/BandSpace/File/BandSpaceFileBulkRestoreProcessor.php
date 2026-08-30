<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\File;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\File\BandSpaceFileBulkRestore;
use App\Entity\User;
use App\Procedure\BandSpace\BandSpaceFileBulkRestoreProcedure;
use App\Security\BandSpace\BandSpaceMemberChecker;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProcessorInterface<BandSpaceFileBulkRestore, void>
 */
readonly class BandSpaceFileBulkRestoreProcessor implements ProcessorInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private BandSpaceFileBulkRestoreProcedure $bulkRestoreProcedure,
        private Security $security,
    ) {
    }

    /**
     * @param BandSpaceFileBulkRestore $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace, $membership] = $this->memberChecker->checkMemberForWrite((string) $uriVariables['bandSpaceId'], $user);

        $this->bulkRestoreProcedure->restore($bandSpace, $membership, $data->fileIds, $user);
    }
}
