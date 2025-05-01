<?php

namespace App\Service\Mail\Brevo\User;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class ResetPasswordEmail
{
    private const TEMPLATE_ID = '2';

    public function __construct(private readonly MailerInterface $mailer)
    {
    }

    public function send(string $recipientEmail, string $username, string $changePasswordLink): void
    {
        $email = (new Email())
            ->from(new Address('no-reply@musicall.com', 'MusicAll'))
            ->to(new Address($recipientEmail, $username))
            ->text('Réinitialisation du mot de passe');
        $email->getHeaders()
            ->addTextHeader('templateId', self::TEMPLATE_ID)
            ->addParameterizedHeader('params', 'params', [
                'change_password_link' => $changePasswordLink,
                'username'          => $username,
            ]);
        $this->mailer->send($email);
    }
}