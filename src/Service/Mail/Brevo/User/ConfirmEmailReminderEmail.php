<?php

declare(strict_types=1);

namespace App\Service\Mail\Brevo\User;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class ConfirmEmailReminderEmail
{
    private const string TEMPLATE_ID = '9';

    public function __construct(private readonly MailerInterface $mailer)
    {
    }

    public function send(string $recipientEmail, string $username, string $confirmationLink): void
    {
        $email = new Email()
            ->from(new Address('no-reply@musicall.com', 'MusicAll'))
            ->to(new Address($recipientEmail, $username))
            ->text('Confirmez votre adresse email');
        $email->getHeaders()
            ->addTextHeader('templateId', self::TEMPLATE_ID)
            ->addParameterizedHeader('params', 'params', [
                'username' => $username,
                'confirmation_link' => $confirmationLink,
            ]);
        $this->mailer->send($email);
    }
}
