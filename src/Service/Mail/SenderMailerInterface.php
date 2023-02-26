<?php

namespace App\Service\Mail;
interface SenderMailerInterface
{
    public function send(array $body): void;
}