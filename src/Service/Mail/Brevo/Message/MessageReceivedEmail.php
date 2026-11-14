<?php declare(strict_types=1);

namespace App\Service\Mail\Brevo\Message;

use App\Messenger\MessageReceivedEmailStamp;
use App\Service\Mail\Brevo\BrevoTemplateId;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Dispatched on the bus rather than through the mailer, which has no way to add a stamp, so the email
 * carries its thread and recipient to the worker without telling Brevo.
 */
class MessageReceivedEmail
{
    private const array TEMPLATE_IDS = ['fr' => '4', 'en' => '16'];

    public function __construct(private readonly MessageBusInterface $bus)
    {
    }

    public function send(
        string $recipientEmail,
        string $username,
        string $senderUsername,
        string $messageUrl,
        string $locale,
        string $threadId,
        string $recipientId,
    ): void {
        $email = (new Email())
            ->from(new Address('no-reply@musicall.com', 'MusicAll'))
            ->to(new Address($recipientEmail, $username))
            ->text('Tu as reçu un message privé');
        $email->getHeaders()
            ->addTextHeader('templateId', BrevoTemplateId::forLocale(self::TEMPLATE_IDS, $locale))
            ->addParameterizedHeader('params', 'params', [
                'username'        => $username,
                'sender_username' => $senderUsername,
                'message_url'     => $messageUrl,
            ]);
        $this->bus->dispatch(new SendEmailMessage($email), [new MessageReceivedEmailStamp($threadId, $recipientId)]);
    }
}
