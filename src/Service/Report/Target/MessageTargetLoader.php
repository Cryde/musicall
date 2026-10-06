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
        $message = Uuid::isValid($id) ? $this->messageRepository->find($id) : null;
        if (
            !$message instanceof Message
            || $message->isDeleted()
            || $message->thread->isChannel()
            || !$this->threadAccess->isOneOfParticipant($message->thread, $reporter)
        ) {
            return null;
        }

        return new ReportTarget($message->author, ReportTarget::text($message->content), ['thread_id' => (string) $message->thread->id]);
    }
}
