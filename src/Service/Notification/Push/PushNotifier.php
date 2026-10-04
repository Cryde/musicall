<?php declare(strict_types=1);

namespace App\Service\Notification\Push;

use App\Entity\User;
use App\Repository\User\DeviceTokenRepository;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\ApiConnectionFailed;
use Kreait\Firebase\Exception\Messaging\QuotaExceeded;
use Kreait\Firebase\Exception\Messaging\ServerError;
use Kreait\Firebase\Exception\Messaging\ServerUnavailable;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\MulticastSendReport;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Messaging\SendReport;
use Psr\Log\LoggerInterface;

/**
 * Sends one notification to every device of a user through FCM. Never throws: a push is a best
 * effort side effect, and tokens FCM no longer knows are deleted on the way.
 */
readonly class PushNotifier
{
    /** The channel the Android app creates on first launch. */
    private const string ANDROID_CHANNEL_ID = 'musicall_default';

    private const array TRANSIENT_ERRORS = [
        ApiConnectionFailed::class,
        QuotaExceeded::class,
        ServerError::class,
        ServerUnavailable::class,
    ];

    public function __construct(
        private Messaging $messaging,
        private DeviceTokenRepository $deviceTokenRepository,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<non-empty-string, string> $data
     */
    public function sendToUser(User $user, string $title, string $body, array $data = []): PushOutcome
    {
        $tokens = $this->deviceTokenRepository->findTokensForUser($user);
        if ($tokens === []) {
            return PushOutcome::Finished;
        }

        $message = CloudMessage::new()
            ->withNotification(Notification::create($title, $body))
            ->withData($data)
            ->withAndroidConfig(AndroidConfig::fromArray([
                'priority' => 'high',
                'notification' => ['channel_id' => self::ANDROID_CHANNEL_ID],
            ]));

        try {
            $report = $this->messaging->sendMulticast($message, $tokens);
        } catch (\Throwable $throwable) {
            // Credentials missing or unreadable, mostly: a retry would fail the same way.
            $this->logger->error('Push notification could not be sent', ['user_id' => $user->id, 'exception' => $throwable]);

            return PushOutcome::Finished;
        }

        try {
            $this->deviceTokenRepository->deleteByTokens([...$report->unknownTokens(), ...$report->invalidTokens()]);
        } catch (\Throwable $throwable) {
            // Swallowed: the push already went out, and a Messenger retry would deliver it twice.
            $this->logger->error('Dead push tokens could not be deleted', ['user_id' => $user->id, 'exception' => $throwable]);
        }

        return $this->settle($report, $user);
    }

    private function settle(MulticastSendReport $report, User $user): PushOutcome
    {
        $failures = $report->failures()
            ->filter(static fn (SendReport $item): bool => !$item->messageWasSentToUnknownToken() && !$item->messageTargetWasInvalid());

        foreach ($failures->getItems() as $failure) {
            $this->logger->error('Push notification failed for one device', ['user_id' => $user->id, 'exception' => $failure->error()]);
        }

        // A retry resends to every device, so it is only safe when none of them got it. A partial
        // delivery is accepted as is.
        $nothingDelivered = $report->successes()->count() === 0;
        $onlyTransient = $failures->count() > 0
            && $failures->filter(fn (SendReport $item): bool => !$this->isTransient($item))->count() === 0;

        return $nothingDelivered && $onlyTransient ? PushOutcome::RetryLater : PushOutcome::Finished;
    }

    private function isTransient(SendReport $item): bool
    {
        return array_any(self::TRANSIENT_ERRORS, static fn (string $class): bool => $item->error() instanceof $class);
    }
}
