<?php

declare(strict_types=1);

namespace App\Service\Mail\Brevo\BandSpace;

use App\Service\Mail\Brevo\BrevoTemplateId;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class AgendaAvailabilityReminderEmail
{
    private const array TEMPLATE_IDS = ['fr' => '25', 'en' => '26'];

    public function __construct(
        private MailerInterface $mailer,
        private TranslatorInterface $translator,
    ) {
    }

    public function send(
        string $recipientEmail,
        string $username,
        string $actorUsername,
        string $bandSpaceName,
        string $entryTitle,
        string $occurrenceLabel,
        string $agendaUrl,
        string $locale,
    ): void {
        $email = new Email()
            ->from(new Address('no-reply@musicall.com', 'MusicAll'))
            ->to(new Address($recipientEmail, $username))
            ->text($this->translator->trans(
                'band_space.availability.reminder_email_text',
                ['actor' => $actorUsername, 'entry' => $entryTitle],
                locale: $locale,
            ));
        $email->getHeaders()
            ->addTextHeader('templateId', BrevoTemplateId::forLocale(self::TEMPLATE_IDS, $locale))
            ->addParameterizedHeader('params', 'params', [
                'username' => $username,
                'actor_username' => $actorUsername,
                'band_space_name' => $bandSpaceName,
                'entry_title' => $entryTitle,
                'occurrence_label' => $occurrenceLabel,
                'agenda_url' => $agendaUrl,
            ]);
        $this->mailer->send($email);
    }
}
