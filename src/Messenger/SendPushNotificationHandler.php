<?php declare(strict_types=1);

namespace App\Messenger;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Notification\Push\PushNotifier;
use App\Service\Notification\Push\PushOutcome;
use App\Service\User\UserNotificationPreferenceChecker;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;

#[AsMessageHandler]
readonly class SendPushNotificationHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private PushNotifier $pushNotifier,
        private UserNotificationPreferenceChecker $preferenceChecker,
    ) {
    }

    public function __invoke(SendPushNotification $message): void
    {
        $user = $this->userRepository->find($message->userId);
        // A closed or suspended account gets nothing, whatever was queued for it before.
        if (!$user instanceof User || !$user->isPubliclyVisible()) {
            return;
        }
        // Read when sent rather than when queued, so turning a category off also drops what is waiting.
        if ($message->category !== null && !$this->preferenceChecker->canReceivePush($user, $message->category)) {
            return;
        }

        $outcome = $this->pushNotifier->sendToUser($user, $message->title, $message->body, $message->data);

        if ($outcome === PushOutcome::RetryLater) {
            throw new RecoverableMessageHandlingException('FCM unreachable for every device, retrying');
        }
    }
}
