<?php

declare(strict_types=1);

namespace App\Service\Mail\Brevo\User;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class EmailVerificationReminderEmail
{
    private const string TEMPLATE_ID = '11';

    public function __construct(private readonly MailerInterface $mailer)
    {
    }

    public function send(string $recipientEmail, string $username, string $code, string $verificationUrl): void
    {
        $email = new Email()
            ->from(new Address('no-reply@musicall.com', 'MusicAll'))
            ->to(new Address($recipientEmail, $username))
            ->text('Rappel : vérifiez votre adresse email');
        $email->getHeaders()
            ->addTextHeader('templateId', self::TEMPLATE_ID)
            ->addParameterizedHeader('params', 'params', [
                'username' => $username,
                'code' => $code,
                'verification_url' => $verificationUrl,
            ]);
        $this->mailer->send($email);
    }
}
