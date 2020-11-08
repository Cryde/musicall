<?php

namespace App\Service\Mail\Contact;

use App\Service\Mail\ArrayMailBuilder;
use App\Service\Mail\Mailer;

class ContactMail
{
    const TEMPLATE_ID = 1876336;

    private Mailer $mailer;
    private ArrayMailBuilder $arrayMailBuilder;
    private string $email;

    public function __construct(string $email, Mailer $mailer, ArrayMailBuilder $arrayMailBuilder)
    {
        $this->mailer = $mailer;
        $this->arrayMailBuilder = $arrayMailBuilder;
        $this->email = $email;
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
                    'message' => nl2br($message),
                ]
            )
        );
    }
}
