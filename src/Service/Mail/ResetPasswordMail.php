<?php

namespace App\Service\Mail;

class ResetPasswordMail
{
    final const TEMPLATE_ID = 1_279_970;

    public function __construct(private readonly Mailer $mailer, private readonly ArrayMailBuilder $arrayMailBuilder)
    {
    }

    public function send(string $recipientEmail, string $username, string $changePasswordLink)
    {
        $this->mailer->send(
            $this->arrayMailBuilder->build(
                $recipientEmail,
                self::TEMPLATE_ID,
                "Demande de réinitialisation du mot de passe",
                [
                    'username'             => $username,
                    'change_password_link' => $changePasswordLink,
                ]
            )
        );
    }
}
