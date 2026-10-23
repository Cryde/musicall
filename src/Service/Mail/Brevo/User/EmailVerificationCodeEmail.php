<?php declare(strict_types=1);

namespace App\Service\Mail\Brevo\User;

use App\Service\Mail\Brevo\BrevoTemplateId;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class EmailVerificationCodeEmail
{
    private const array TEMPLATE_IDS = ['fr' => '10', 'en' => '19'];

    public function __construct(private readonly MailerInterface $mailer)
    {
    }

    public function send(string $recipientEmail, string $username, string $code, string $locale): void
    {
        $email = new Email()
            ->from(new Address('no-reply@musicall.com', 'MusicAll'))
            ->to(new Address($recipientEmail, $username))
            ->text('Ton code de vérification');
        $email->getHeaders()
            ->addTextHeader('templateId', BrevoTemplateId::forLocale(self::TEMPLATE_IDS, $locale))
            ->addParameterizedHeader('params', 'params', [
                'code'     => $code,
                'username' => $username,
            ]);
        $this->mailer->send($email);
    }
}
