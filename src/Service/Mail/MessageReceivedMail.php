<?php

namespace App\Service\Mail;

class MessageReceivedMail
{
    final const TEMPLATE_ID = 1_474_537;

    public function __construct(private readonly Mailer $mailer, private readonly ArrayMailBuilder $arrayMailBuilder)
    {
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
