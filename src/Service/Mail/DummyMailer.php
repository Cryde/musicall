<?php

namespace App\Service\Mail;
class DummyMailer implements SenderMailerInterface
{
    public function send(array $body): void
    {
        // do nothing
    }
}
