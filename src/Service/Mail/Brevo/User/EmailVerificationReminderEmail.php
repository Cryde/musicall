<?php

declare(strict_types=1);

namespace App\Service\Mail\Brevo\User;

use App\Service\Mail\Brevo\BrevoTemplateId;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class EmailVerificationReminderEmail
{
    private const array TEMPLATE_IDS = ['fr' => '11', 'en' => '21'];

    public function __construct(private readonly MailerInterface $mailer)
    {
    }

    public function send(
        string $recipientEmail,
        string $username,
        string $code,
        string $verificationUrl,
        string $locale,
    ): void {
        $email = new Email()
            ->from(new Address('no-reply@musicall.com', 'MusicAll'))
            ->to(new Address($recipientEmail, $username))
            ->text('Rappel : vérifiez votre adresse email');
        $email->getHeaders()
            ->addTextHeader('templateId', BrevoTemplateId::forLocale(self::TEMPLATE_IDS, $locale))
            ->addParameterizedHeader('params', 'params', [
                'username' => $username,
                'code' => $code,
                'verification_url' => $verificationUrl,
            ]);
        $this->mailer->send($email);
    }
}
