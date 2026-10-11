<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\BandSpace\BandSpaceOpenedItemRecord;
use App\Entity\User;
use App\Enum\BandSpace\BandSpaceSearchResultType;
use App\Repository\BandSpace\BandSpaceOpenedItemRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use DateTimeImmutable;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Records an open in the caller's own history (#1047).
 *
 * Outside the Band Space write chain on purpose. The read check, not the write one, because the
 * grace period before a deletion is when members open things to retrieve them. No activity and no
 * realtime signal, since this is the member's own history, not band data. The item is not looked up:
 * the history is only read back through the space's live tables, where a stray id never resolves.
 *
 * @implements ProcessorInterface<BandSpaceOpenedItemRecord, null>
 */
readonly class BandSpaceOpenedItemRecordProcessor implements ProcessorInterface
{
    /** The issue's cap: enough for any palette order, bounded without a cleanup job. */
    private const int HISTORY_SIZE = 50;

    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private BandSpaceOpenedItemRepository $openedItemRepository,
        private Security $security,
    ) {
    }

    /**
     * @param BandSpaceOpenedItemRecord $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        [, $membership] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $this->openedItemRepository->recordOpen(
            $membership,
            BandSpaceSearchResultType::from((string) $data->type),
            mb_strtolower((string) $data->resourceId),
            new DateTimeImmutable(),
        );
        $this->openedItemRepository->pruneBeyond($membership, self::HISTORY_SIZE);

        return null;
    }
}
