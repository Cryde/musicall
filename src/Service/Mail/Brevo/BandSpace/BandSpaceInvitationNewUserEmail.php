<?php declare(strict_types=1);

namespace App\Service\Mail\Brevo\BandSpace;

use App\Service\Mail\Brevo\BrevoTemplateId;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

readonly class BandSpaceInvitationNewUserEmail
{
    private const array TEMPLATE_IDS = ['fr' => '12', 'en' => '22'];

    public function __construct(private MailerInterface $mailer)
    {
    }

    public function send(string $recipientEmail, string $bandSpaceName, string $registerUrl, string $locale): void
    {
        $email = new Email()
            ->from(new Address('no-reply@musicall.com', 'MusicAll'))
            ->to(new Address($recipientEmail))
            ->text('Vous avez été invité à rejoindre ' . $bandSpaceName . ' sur MusicAll');
        $email->getHeaders()
            ->addTextHeader('templateId', BrevoTemplateId::forLocale(self::TEMPLATE_IDS, $locale))
            ->addParameterizedHeader('params', 'params', [
                'band_space_name' => $bandSpaceName,
                'register_url' => $registerUrl,
            ]);
        $this->mailer->send($email);
    }
}
