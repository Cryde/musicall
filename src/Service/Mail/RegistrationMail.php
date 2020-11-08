<?php

namespace App\Service\Mail;

class RegistrationMail
{
    const TEMPLATE_ID = 1246593;

    private Mailer $mailer;
    private ArrayMailBuilder $arrayMailBuilder;

    public function __construct(Mailer $mailer, ArrayMailBuilder $arrayMailBuilder)
    {
        $this->mailer = $mailer;
        $this->arrayMailBuilder = $arrayMailBuilder;
    }

    public function send(string $recipientEmail, string $username, string $confirmEmail)
    {
        $this->mailer->send(
            $this->arrayMailBuilder->build(
                $recipientEmail,
                self::TEMPLATE_ID,
                "Confirmer votre email",
                [
                    'username'          => $username,
                    'confirmation_link' => $confirmEmail,
                ]
            )
        );
    }
}
