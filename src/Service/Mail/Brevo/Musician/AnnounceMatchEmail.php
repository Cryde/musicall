<?php

declare(strict_types=1);

namespace App\Service\Mail\Brevo\Musician;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/** A new announce answers one of the member's (#1082). */
readonly class AnnounceMatchEmail
{
    private const string TEMPLATE_ID = '14';

    public function __construct(private MailerInterface $mailer)
    {
    }

    public function send(
        string $recipientEmail,
        string $username,
        string $authorUsername,
        string $announceHeadline,
        string $locationName,
        int $distanceKm,
        string $answeredHeadline,
        string $profileUrl,
    ): void {
        $email = (new Email())
            ->from(new Address('no-reply@musicall.com', 'MusicAll'))
            ->to(new Address($recipientEmail, $username))
            ->text('Une nouvelle annonce correspond à la vôtre');
        $email->getHeaders()
            ->addTextHeader('templateId', self::TEMPLATE_ID)
            ->addParameterizedHeader('params', 'params', [
                'username' => $username,
                'author_username' => $authorUsername,
                'announce_headline' => $announceHeadline,
                'location_name' => $locationName,
                'distance_km' => $distanceKm,
                'answered_headline' => $answeredHeadline,
                'profile_url' => $profileUrl,
            ]);
        $this->mailer->send($email);
    }
}
