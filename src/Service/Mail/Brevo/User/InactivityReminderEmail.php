<?php

declare(strict_types=1);

namespace App\Service\Mail\Brevo\User;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class InactivityReminderEmail
{
    private const string TEMPLATE_ID = '8';

    public function __construct(private readonly MailerInterface $mailer)
    {
    }

    public function send(string $recipientEmail, string $username, string $lastLoginDate): void
    {
        $email = new Email()
            ->from(new Address('no-reply@musicall.com', 'MusicAll'))
            ->to(new Address($recipientEmail, $username))
            ->text('Vous nous manquez sur MusicAll !');
        $email->getHeaders()
            ->addTextHeader('templateId', self::TEMPLATE_ID)
            ->addParameterizedHeader('params', 'params', [
                'username' => $username,
                'last_login_date' => $lastLoginDate,
            ]);
        $this->mailer->send($email);
    }
}
