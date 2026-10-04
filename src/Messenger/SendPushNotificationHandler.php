<?php declare(strict_types=1);

namespace App\Messenger;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Notification\Push\PushNotifier;
use App\Service\Notification\Push\PushOutcome;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;

#[AsMessageHandler]
readonly class SendPushNotificationHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private PushNotifier $pushNotifier,
    ) {
    }

    public function __invoke(SendPushNotification $message): void
    {
        $user = $this->userRepository->find($message->userId);
        if (!$user instanceof User || $user->isDeleted()) {
            return;
        }

        $outcome = $this->pushNotifier->sendToUser($user, $message->title, $message->body, $message->data);

        if ($outcome === PushOutcome::RetryLater) {
            throw new RecoverableMessageHandlingException('FCM unreachable for every device, retrying');
        }
    }
}
