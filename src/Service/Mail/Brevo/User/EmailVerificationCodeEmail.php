<?php declare(strict_types=1);

namespace App\Service\Mail\Brevo\User;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class EmailVerificationCodeEmail
{
    private const string TEMPLATE_ID = '10';

    public function __construct(private readonly MailerInterface $mailer)
    {
    }

    public function send(string $recipientEmail, string $username, string $code): void
    {
        $email = new Email()
            ->from(new Address('no-reply@musicall.com', 'MusicAll'))
            ->to(new Address($recipientEmail, $username))
            ->text('Votre code de vérification');
        $email->getHeaders()
            ->addTextHeader('templateId', self::TEMPLATE_ID)
            ->addParameterizedHeader('params', 'params', [
                'code'     => $code,
                'username' => $username,
            ]);
        $this->mailer->send($email);
    }
}
