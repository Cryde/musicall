<?php

namespace App\Service\Identifier;

use Symfony\Component\HttpFoundation\Request;

class RequestIdentifier
{
    private string $secret;

    public function __construct(string $secret)
    {
        $this->secret = $secret;
    }

    public function fromRequest(Request $request): string
    {
        return hash('sha512', $request->getClientIp() . $this->secret);
    }
}
