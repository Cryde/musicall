<?php

namespace App\Service\Mail\Brevo\User;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class ConfirmRegistrationEmail
{
    private const TEMPLATE_ID = '1';

    public function __construct(private readonly MailerInterface $mailer)
    {
    }

    public function send(string $recipientEmail, string $username, string $confirmEmail)
    {
        $email = (new Email())
            ->from('no-reply@musicall.com')
            ->to($recipientEmail)
            ->text('Confirmer votre email');
        $email->getHeaders()
            ->addTextHeader('templateId', self::TEMPLATE_ID)
            ->addParameterizedHeader('params', 'params', [
                'confirmation_link' => $confirmEmail,
                'username'          => $username,
            ]);
        $this->mailer->send($email);
    }
}