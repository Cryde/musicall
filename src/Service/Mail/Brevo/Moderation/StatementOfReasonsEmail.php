<?php

declare(strict_types=1);

namespace App\Service\Mail\Brevo\Moderation;

use App\Service\Builder\Moderation\StatementOfReasons;
use App\Service\Mail\Brevo\BrevoTemplateId;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/** The statement of reasons of a moderation decision (#1126); the template only lays out the params. */
class StatementOfReasonsEmail
{
    // Placeholder until the Brevo templates exist: replace both ids with the real ones before merging.
    private const array TEMPLATE_IDS = ['fr' => '0', 'en' => '0'];

    public function __construct(private readonly MailerInterface $mailer)
    {
    }

    public function send(string $recipientEmail, string $username, StatementOfReasons $statement, string $locale): void
    {
        $email = new Email()
            ->from(new Address('no-reply@musicall.com', 'MusicAll'))
            ->to(new Address($recipientEmail, $username))
            ->text('Décision de modération concernant ton compte MusicAll.');
        $email->getHeaders()
            ->addTextHeader('templateId', BrevoTemplateId::forLocale(self::TEMPLATE_IDS, $locale))
            ->addParameterizedHeader('params', 'params', [
                'username' => $username,
                'decision' => $statement->decision,
                'decision_date' => $statement->decisionDate,
                'facts' => $statement->facts,
                'reported_content' => $statement->reportedContent,
                'basis' => $statement->basis,
                'human_decision' => $statement->humanDecision,
                'redress' => $statement->redress,
            ]);
        $this->mailer->send($email);
    }
}
