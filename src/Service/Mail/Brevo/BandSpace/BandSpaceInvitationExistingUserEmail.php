<?php declare(strict_types=1);

namespace App\Service\Mail\Brevo\BandSpace;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

readonly class BandSpaceInvitationExistingUserEmail
{
    private const string TEMPLATE_ID = '13';

    public function __construct(private MailerInterface $mailer)
    {
    }

    public function send(string $recipientEmail, string $username, string $bandSpaceName, string $invitationUrl): void
    {
        $email = new Email()
            ->from(new Address('no-reply@musicall.com', 'MusicAll'))
            ->to(new Address($recipientEmail, $username))
            ->text('Vous avez été invité à rejoindre ' . $bandSpaceName . ' sur MusicAll');
        $email->getHeaders()
            ->addTextHeader('templateId', self::TEMPLATE_ID)
            ->addParameterizedHeader('params', 'params', [
                'username' => $username,
                'band_space_name' => $bandSpaceName,
                'invitation_url' => $invitationUrl,
            ]);
        $this->mailer->send($email);
    }
}
