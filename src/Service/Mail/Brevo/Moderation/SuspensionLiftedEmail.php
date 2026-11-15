<?php

declare(strict_types=1);

namespace App\Service\Mail\Brevo\Moderation;

use App\Service\Mail\Brevo\BrevoDate;
use App\Service\Mail\Brevo\BrevoTemplateId;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/** Tells a member their account is active again (#1126). */
class SuspensionLiftedEmail
{
    // Placeholder until the Brevo templates exist: replace both ids with the real ones before merging.
    private const array TEMPLATE_IDS = ['fr' => '0', 'en' => '0'];

    public function __construct(private readonly MailerInterface $mailer)
    {
    }

    public function send(string $recipientEmail, string $username, \DateTimeImmutable $liftedAt, string $locale): void
    {
        $email = new Email()
            ->from(new Address('no-reply@musicall.com', 'MusicAll'))
            ->to(new Address($recipientEmail, $username))
            ->text('Ton compte MusicAll est de nouveau actif.');
        $email->getHeaders()
            ->addTextHeader('templateId', BrevoTemplateId::forLocale(self::TEMPLATE_IDS, $locale))
            ->addParameterizedHeader('params', 'params', [
                'username' => $username,
                'lifted_date' => BrevoDate::format($liftedAt, $locale),
            ]);
        $this->mailer->send($email);
    }
}
