<?php declare(strict_types=1);

namespace App\Service\Mail\Brevo\Message;

use App\Service\Mail\Brevo\BrevoTemplateId;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class MessageReceivedEmail
{
    private const array TEMPLATE_IDS = ['fr' => '4', 'en' => '16'];

    public function __construct(private readonly MailerInterface $mailer)
    {
    }

    public function send(
        string $recipientEmail,
        string $username,
        string $senderUsername,
        string $messageUrl,
        string $locale,
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
        $this->mailer->send($email);
    }
}
