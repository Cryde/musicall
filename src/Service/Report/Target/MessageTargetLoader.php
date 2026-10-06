<?php declare(strict_types=1);

namespace App\Service\Report\Target;

use App\Entity\Message\Message;
use App\Entity\User;
use App\Enum\Report\ReportTargetType;
use App\Repository\Message\MessageRepository;
use App\Service\Access\ThreadAccess;
use App\Service\Report\ReportTarget;
use Ramsey\Uuid\Uuid;

/** A direct message, reportable only by a participant of its conversation. */
readonly class MessageTargetLoader implements ReportTargetLoaderInterface
{
    public function __construct(
        private MessageRepository $messageRepository,
        private ThreadAccess $threadAccess,
    ) {
    }

    public function type(): ReportTargetType
    {
        return ReportTargetType::Message;
    }

    public function load(string $id, User $reporter): ?ReportTarget
    {
        $message = $this->find($id);

        return $message instanceof Message && $this->threadAccess->isOneOfParticipant($message->thread, $reporter)
            ? $this->snapshot($message)
            : null;
    }

    public function current(string $id): ?ReportTarget
    {
        $message = $this->find($id);

        return $message instanceof Message ? $this->snapshot($message) : null;
    }

    private function find(string $id): ?Message
    {
        $message = Uuid::isValid($id) ? $this->messageRepository->find($id) : null;

        return $message instanceof Message && !$message->isDeleted() && !$message->thread->isChannel() ? $message : null;
    }

    private function snapshot(Message $message): ReportTarget
    {
        return new ReportTarget($message->author, ReportTarget::text($message->content), ['thread_id' => (string) $message->thread->id]);
    }
}
