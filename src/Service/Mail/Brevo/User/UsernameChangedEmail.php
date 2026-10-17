<?php

declare(strict_types=1);

namespace App\Service\Mail\Brevo\User;

use App\Service\Mail\Brevo\BrevoTemplateId;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class UsernameChangedEmail
{
    private const array TEMPLATE_IDS = ['fr' => '7', 'en' => '18'];

    public function __construct(private readonly MailerInterface $mailer)
    {
    }

    public function send(
        string $recipientEmail,
        string $oldUsername,
        string $newUsername,
        \DateTimeImmutable $changedAt,
        string $locale,
    ): void {
        $email = new Email()
            ->from(new Address('no-reply@musicall.com', 'MusicAll'))
            ->to(new Address($recipientEmail, $newUsername))
            ->text(sprintf('Votre nom d\'utilisateur a été modifié de %s à %s.', $oldUsername, $newUsername));
        $email->getHeaders()
            ->addTextHeader('templateId', BrevoTemplateId::forLocale(self::TEMPLATE_IDS, $locale))
            ->addParameterizedHeader('params', 'params', [
                'old_username' => $oldUsername,
                'new_username' => $newUsername,
                'changed_at' => $changedAt->format('d/m/Y à H:i'),
            ]);
        $this->mailer->send($email);
    }
}
