<?php

namespace App\Service\Mail\Brevo\Contact;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class ContactUsEmail
{
    private const TEMPLATE_ID = '3';

    public function __construct(
        private readonly string                 $email,
        private readonly MailerInterface        $mailer,
        private readonly HtmlSanitizerInterface $appOnlybrSanitizer
    ) {
    }

    public function send(string $name, string $emailAddress, string $message): void
    {
        $email = (new Email())
            ->from(new Address('no-reply@musicall.com', 'MusicAll'))
            ->to($this->email)
            ->text('[ADMIN] Contact reçu depuis MusicAll');
        $email->getHeaders()
            ->addTextHeader('templateId', self::TEMPLATE_ID)
            ->addParameterizedHeader('params', 'params', [
                'name'    => $name,
                'email'   => $emailAddress,
                'message' => $this->appOnlybrSanitizer->sanitize(nl2br($message)),
            ]);
        $this->mailer->send($email);
    }
}