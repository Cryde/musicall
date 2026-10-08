<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Event\MessageSentEvent;
use App\Repository\Message\MessageThreadMetaRepository;
use App\Service\Mail\Brevo\Message\MessageReceivedEmail;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Sends the message-received email. The throttle (one email per unread
 * streak, #533), preference and deleted-user checks, and the pending-flag
 * flip all live in MessageSenderProcedure::shouldNotify - by the time we
 * dispatch the event, we have already decided an email should go out. The
 * one thing done here is to release that flag when the email fails (#1151),
 * so the thread is not silenced until the recipient reads it.
 *
 * Kept as a listener (rather than calling the email service inline from
 * the procedure) so future side-effects on message-sent (analytics,
 * push notification, etc.) can plug in without touching the procedure.
 */
#[AsEventListener]
readonly class MessageSentListener
{
    public function __construct(
        private MessageReceivedEmail        $messageReceivedEmail,
        private RouterInterface             $router,
        private MessageThreadMetaRepository $messageThreadMetaRepository,
        private LoggerInterface             $logger,
    ) {
    }

    public function __invoke(MessageSentEvent $event): void
    {
        // The message is already saved: nothing here may turn its send into a 500 (#1151), which the
        // sender reads as "not sent" and answers by sending it again.
        try {
            $baseUrl = $this->router->generate('app_homepage', [], UrlGeneratorInterface::ABSOLUTE_URL);
            $this->messageReceivedEmail->send(
                $event->recipient->email,
                $event->recipient->username,
                $event->sender->username,
                $baseUrl . 'messages/' . $event->thread->id,
            );
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send the message received email', [
                'thread_id' => (string) $event->thread->id,
                'recipient_id' => (string) $event->recipient->id,
                'exception' => $e,
            ]);
            $this->releasePendingNotification($event);
        }
    }

    /**
     * Guarded on its own: a mailer that failed for an infrastructure reason is exactly when the
     * database may be failing too, and this must not bring the 500 back.
     */
    private function releasePendingNotification(MessageSentEvent $event): void
    {
        try {
            $this->messageThreadMetaRepository->releasePendingNotification($event->thread, $event->recipient);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to release the message notification throttle', [
                'thread_id' => (string) $event->thread->id,
                'recipient_id' => (string) $event->recipient->id,
                'exception' => $e,
            ]);
        }
    }
}
