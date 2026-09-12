<?php declare(strict_types=1);

namespace App\State\Processor\BandSpace\Chat;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\Repository\Message\MessageThreadMetaRepository;
use App\Security\BandSpace\BandSpaceMemberChecker;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProcessorInterface<mixed, void>
 */
readonly class ChatReadProcessor implements ProcessorInterface
{
    public function __construct(
        private BandSpaceMemberChecker $memberChecker,
        private MessageThreadMetaRepository $messageThreadMetaRepository,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        // checkMember(), not checkMemberForWrite(): see BandSpaceWriteGuardCoverageTest's allow list.
        // A space in its grace period stays readable, so a member has to be able to stop being told
        // there is something unread in a conversation they can still open.
        [$bandSpace] = $this->memberChecker->checkMember((string) $uriVariables['bandSpaceId'], $user);

        $this->messageThreadMetaRepository->markChannelsReadForUser($bandSpace, $user);
    }
}
