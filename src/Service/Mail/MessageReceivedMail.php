<?php

namespace App\Service\Mail;

class MessageReceivedMail
{
    const TEMPLATE_ID = 1474537;

    private Mailer $mailer;
    private ArrayMailBuilder $arrayMailBuilder;

    public function __construct(Mailer $mailer, ArrayMailBuilder $arrayMailBuilder)
    {
        $this->mailer = $mailer;
        $this->arrayMailBuilder = $arrayMailBuilder;
    }

    public function send(string $recipientEmail, string $username)
    {
        $this->mailer->send(
            $this->arrayMailBuilder->build(
                $recipientEmail,
                self::TEMPLATE_ID,
                "Vous avez reçu un message",
                [
                    'username' => $username,
                ]
            )
        );
    }
}
