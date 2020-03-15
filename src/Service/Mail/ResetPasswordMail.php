<?php

namespace App\Service\Mail;

class ResetPasswordMail
{
    const TEMPLATE_ID = 1279970;
    /**
     * @var Mailer
     */
    private Mailer $mailer;
    /**
     * @var ArrayMailBuilder
     */
    private ArrayMailBuilder $arrayMailBuilder;

    public function __construct(Mailer $mailer, ArrayMailBuilder $arrayMailBuilder)
    {
        $this->mailer = $mailer;
        $this->arrayMailBuilder = $arrayMailBuilder;
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
