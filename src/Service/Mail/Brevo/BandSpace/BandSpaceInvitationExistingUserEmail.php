<?php declare(strict_types=1);

namespace App\Service\Mail\Brevo\BandSpace;

use App\Service\Mail\Brevo\BrevoTemplateId;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

readonly class BandSpaceInvitationExistingUserEmail
{
    private const array TEMPLATE_IDS = ['fr' => '13', 'en' => '23'];

    public function __construct(private MailerInterface $mailer)
    {
    }

    public function send(
        string $recipientEmail,
        string $username,
        string $bandSpaceName,
        string $invitationUrl,
        string $locale,
    ): void {
        $email = new Email()
            ->from(new Address('no-reply@musicall.com', 'MusicAll'))
            ->to(new Address($recipientEmail, $username))
            ->text('Vous avez été invité à rejoindre ' . $bandSpaceName . ' sur MusicAll');
        $email->getHeaders()
            ->addTextHeader('templateId', BrevoTemplateId::forLocale(self::TEMPLATE_IDS, $locale))
            ->addParameterizedHeader('params', 'params', [
                'username' => $username,
                'band_space_name' => $bandSpaceName,
                'invitation_url' => $invitationUrl,
            ]);
        $this->mailer->send($email);
    }
}
