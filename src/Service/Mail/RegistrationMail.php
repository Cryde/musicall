<?php

namespace App\Service\Mail;

class RegistrationMail
{
    final const TEMPLATE_ID = 1_246_593;

    public function __construct(private readonly Mailer $mailer, private readonly ArrayMailBuilder $arrayMailBuilder)
    {
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
