<?php declare(strict_types=1);

namespace App\Tests\Double;

use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Messaging\AppInstance;
use Kreait\Firebase\Messaging\Message;
use Kreait\Firebase\Messaging\Messages;
use Kreait\Firebase\Messaging\MessageTarget;
use Kreait\Firebase\Messaging\MulticastSendReport;
use Kreait\Firebase\Messaging\RegistrationToken;
use Kreait\Firebase\Messaging\RegistrationTokens;
use Kreait\Firebase\Messaging\SendReport;
use Kreait\Firebase\Messaging\Topic;

/**
 * Stands in for FCM: records every multicast and answers each token with success, unless a test
 * seeded a failure for it with failToken() or made the whole call throw with throwOnSend().
 */
final class RecordingFirebaseMessaging implements Messaging
{
    /** @var list<array{message: array<mixed>, tokens: list<string>}> */
    public array $multicasts = [];

    /** @var array<string, MessagingException> */
    private array $failures = [];

    private ?\Throwable $sendError = null;

    public function failToken(string $token, MessagingException $error): void
    {
        $this->failures[$token] = $error;
    }

    public function throwOnSend(\Throwable $error): void
    {
        $this->sendError = $error;
    }

    public function sendMulticast(Message|array $message, RegistrationTokens|RegistrationToken|array|string $registrationTokens, bool $validateOnly = false): MulticastSendReport
    {
        if ($this->sendError !== null) {
            throw $this->sendError;
        }

        $tokens = RegistrationTokens::fromValue($registrationTokens)->asStrings();
        $this->multicasts[] = [
            'message' => json_decode((string) json_encode($message), true),
            'tokens' => $tokens,
        ];

        return MulticastSendReport::withItems(array_map(
            function (string $token): SendReport {
                $target = MessageTarget::with(MessageTarget::TOKEN, $token);

                return isset($this->failures[$token])
                    ? SendReport::failure($target, $this->failures[$token])
                    : SendReport::success($target, ['name' => 'projects/musicall-test/messages/0:' . md5($token)]);
            },
            $tokens,
        ));
    }

    public function send(Message|array $message, bool $validateOnly = false): array
    {
        throw new \LogicException('Not used by the application');
    }

    public function sendAll(array|Messages $messages, bool $validateOnly = false): MulticastSendReport
    {
        throw new \LogicException('Not used by the application');
    }

    public function validate(Message|array $message): array
    {
        throw new \LogicException('Not used by the application');
    }

    public function validateRegistrationTokens(RegistrationTokens|RegistrationToken|array|string $registrationTokenOrTokens): array
    {
        throw new \LogicException('Not used by the application');
    }

    public function subscribeToTopic(string|Topic $topic, RegistrationTokens|RegistrationToken|array|string $registrationTokenOrTokens): array
    {
        throw new \LogicException('Not used by the application');
    }

    public function subscribeToTopics(iterable $topics, RegistrationTokens|RegistrationToken|array|string $registrationTokenOrTokens): array
    {
        throw new \LogicException('Not used by the application');
    }

    public function unsubscribeFromTopic(string|Topic $topic, RegistrationTokens|RegistrationToken|array|string $registrationTokenOrTokens): array
    {
        throw new \LogicException('Not used by the application');
    }

    public function unsubscribeFromTopics(array $topics, RegistrationTokens|RegistrationToken|array|string $registrationTokenOrTokens): array
    {
        throw new \LogicException('Not used by the application');
    }

    public function unsubscribeFromAllTopics(RegistrationTokens|RegistrationToken|array|string $registrationTokenOrTokens): array
    {
        throw new \LogicException('Not used by the application');
    }

    public function getAppInstance(RegistrationToken|string $registrationToken): AppInstance
    {
        throw new \LogicException('Not used by the application');
    }
}
