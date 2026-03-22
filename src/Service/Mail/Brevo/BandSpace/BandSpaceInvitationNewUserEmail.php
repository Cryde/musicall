<?php declare(strict_types=1);

namespace App\Service\Mail\Brevo\BandSpace;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

readonly class BandSpaceInvitationNewUserEmail
{
    private const string TEMPLATE_ID = '12';

    public function __construct(private readonly MailerInterface $mailer)
    {
    }

    public function send(string $recipientEmail, string $bandSpaceName, string $registerUrl): void
    {
        $email = new Email()
            ->from(new Address('no-reply@musicall.com', 'MusicAll'))
            ->to(new Address($recipientEmail))
            ->text('Vous avez été invité à rejoindre ' . $bandSpaceName . ' sur MusicAll');
        $email->getHeaders()
            ->addTextHeader('templateId', self::TEMPLATE_ID)
            ->addParameterizedHeader('params', 'params', [
                'band_space_name' => $bandSpaceName,
                'register_url' => $registerUrl,
            ]);
        $this->mailer->send($email);
    }
}
