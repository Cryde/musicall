<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\Task\TaskBulkPatch;
use App\Entity\User;
use App\Procedure\BandSpace\TaskBulkPatchProcedure;
use App\Security\BandSpace\BandSpaceMemberChecker;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProcessorInterface<TaskBulkPatch, void>
 */
readonly class TaskBulkPatchProcessor implements ProcessorInterface
{
    private const ALLOWED_KEYS = ['archived', 'category_id', 'assignee_ids'];

    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private TaskBulkPatchProcedure $taskBulkPatchProcedure,
        private Security $security,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param TaskBulkPatch $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $payload = $this->requestStack->getCurrentRequest()?->toArray() ?? [];
        $patchPayload = array_intersect_key($payload, array_flip(self::ALLOWED_KEYS));

        $this->taskBulkPatchProcedure->patch($bandSpace, $data->taskIds, $patchPayload, $user);
    }
}
