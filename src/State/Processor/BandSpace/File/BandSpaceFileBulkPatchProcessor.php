<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\File;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\File\BandSpaceFileBulkPatch;
use App\Entity\User;
use App\Procedure\BandSpace\BandSpaceFileBulkPatchProcedure;
use App\Security\BandSpace\BandSpaceMemberChecker;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProcessorInterface<BandSpaceFileBulkPatch, void>
 */
readonly class BandSpaceFileBulkPatchProcessor implements ProcessorInterface
{
    /**
     * Moving is the only field the batch applies today. Renaming twelve files to one name is never
     * what somebody meant, and bulk tagging needs add and remove semantics the single file PATCH does
     * not have, since it replaces the whole set.
     */
    private const array ALLOWED_KEYS = ['folder_id'];

    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private BandSpaceFileBulkPatchProcedure $bulkPatchProcedure,
        private Security $security,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param BandSpaceFileBulkPatch $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMemberForWrite((string) $uriVariables['bandSpaceId'], $user);

        // Read raw rather than off the DTO: a move to the root sends folder_id null, so presence has
        // to be told apart from absence. file_ids is excluded by the allowlist so it can never leak
        // into the per file merge-patch.
        $payload = $this->requestStack->getCurrentRequest()?->toArray() ?? [];
        $patchPayload = array_intersect_key($payload, array_flip(self::ALLOWED_KEYS));

        $this->bulkPatchProcedure->patch($bandSpace, $data->fileIds, $patchPayload, $user);
    }
}
