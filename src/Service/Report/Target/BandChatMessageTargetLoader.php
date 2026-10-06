<?php declare(strict_types=1);

namespace App\Service\Report\Target;

use App\Entity\BandSpace\BandSpace;
use App\Entity\BandSpace\BandSpaceMembership;
use App\Entity\Message\Message;
use App\Entity\User;
use App\Enum\Report\ReportTargetType;
use App\Repository\BandSpace\BandSpaceMembershipRepository;
use App\Repository\Message\MessageRepository;
use App\Service\Report\ReportTarget;
use Ramsey\Uuid\Uuid;

/** A band chat message, reportable only by an active member of that band. */
readonly class BandChatMessageTargetLoader implements ReportTargetLoaderInterface
{
    public function __construct(
        private MessageRepository $messageRepository,
        private BandSpaceMembershipRepository $membershipRepository,
    ) {
    }

    public function type(): ReportTargetType
    {
        return ReportTargetType::BandChatMessage;
    }

    public function load(string $id, User $reporter): ?ReportTarget
    {
        $message = $this->find($id);
        $bandSpace = $message?->thread->bandSpace;

        return $message instanceof Message
            && $bandSpace instanceof BandSpace
            && $this->membershipRepository->findMembership($bandSpace, $reporter) instanceof BandSpaceMembership
            ? $this->snapshot($message, $bandSpace)
            : null;
    }

    public function current(string $id): ?ReportTarget
    {
        $message = $this->find($id);
        $bandSpace = $message?->thread->bandSpace;

        return $message instanceof Message && $bandSpace instanceof BandSpace ? $this->snapshot($message, $bandSpace) : null;
    }

    private function find(string $id): ?Message
    {
        $message = Uuid::isValid($id) ? $this->messageRepository->find($id) : null;

        return $message instanceof Message && !$message->isDeleted() && $message->thread->isChannel() ? $message : null;
    }

    private function snapshot(Message $message, BandSpace $bandSpace): ReportTarget
    {
        return new ReportTarget(
            $message->author,
            ReportTarget::text($message->content),
            ['band_space_id' => (string) $bandSpace->id, 'band_space_name' => $bandSpace->name],
        );
    }
}
