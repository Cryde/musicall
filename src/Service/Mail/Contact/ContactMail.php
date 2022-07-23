<?php

namespace App\Service\Mail\Contact;

use App\Service\Mail\ArrayMailBuilder;
use App\Service\Mail\Mailer;
use HtmlSanitizer\SanitizerInterface;

class ContactMail
{
    final const TEMPLATE_ID = 1_876_336;

    public function __construct(
        private readonly string             $email,
        private readonly Mailer             $mailer,
        private readonly ArrayMailBuilder   $arrayMailBuilder,
        private readonly SanitizerInterface $sanitizer
    ) {
    }

    public function send(string $name, string $email, string $message)
    {
        $this->mailer->send(
            $this->arrayMailBuilder->build(
                $this->email,
                self::TEMPLATE_ID,
                "[ADMIN] Contact reçu depuis MusicAll",
                [
                    'name'    => $name,
                    'email'   => $email,
                    'message' => $this->sanitizer->sanitize(nl2br($message)),
                ]
            )
        );
    }
}
