<?php declare(strict_types=1);

namespace App\Service\Identifier;

use Symfony\Component\HttpFoundation\Request;

class RequestIdentifier
{
    public function __construct(private readonly string $secret)
    {
    }

    public function fromRequest(Request $request): string
    {
        return hash('sha512', $request->getClientIp() . $this->secret);
    }
}
