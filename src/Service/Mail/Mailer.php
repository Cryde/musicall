<?php

namespace App\Service\Mail;

use Mailjet\Client;
use Mailjet\Resources;

class Mailer
{
    public function __construct(private readonly Client $client)
    {
    }

    public function send(array $body): void
    {
        $this->client->post(Resources::$Email, ['body' => $body]);
    }
}
