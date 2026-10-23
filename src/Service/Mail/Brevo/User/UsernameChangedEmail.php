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
    // Formatted here, the template only prints it; a locale without its own format reads the French one.
    private const array CHANGED_AT_FORMATS = ['fr' => 'd/m/Y à H:i', 'en' => 'd/m/Y \\a\\t H:i'];

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
            ->text(sprintf('Ton nom d\'utilisateur a été modifié de %s à %s.', $oldUsername, $newUsername));
        $email->getHeaders()
            ->addTextHeader('templateId', BrevoTemplateId::forLocale(self::TEMPLATE_IDS, $locale))
            ->addParameterizedHeader('params', 'params', [
                'old_username' => $oldUsername,
                'new_username' => $newUsername,
                'changed_at' => $changedAt->format(self::CHANGED_AT_FORMATS[$locale] ?? self::CHANGED_AT_FORMATS['fr']),
            ]);
        $this->mailer->send($email);
    }
}
