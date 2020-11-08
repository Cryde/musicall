<?php

namespace App\Service\Mail;

use Mailjet\Client;
use Mailjet\Resources;

class Mailer
{
    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function send(array $body)
    {
        $this->client->post(Resources::$Email, ['body' => $body]);
    }
}
