<?php

namespace App\Tests\Unit\Service\Identifier;

use App\Service\Identifier\RequestIdentifier;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class RequestIdentifierTest extends TestCase
{
    public function testFromRequest()
    {
        $requestIdentifier = new RequestIdentifier('secret');

        $request = new Request();
        $request->initialize([], [], [], [], [], ['REMOTE_ADDR' => '123']);
        $result = $requestIdentifier->fromRequest($request);

        // sha512 of "123secret"
        $this->assertSame('b36dc8a3a4dfaf6620c29555374df8e195914a0fb0f065b20bdbafbd4527644dd43c91734eb3f3c13d631dc9b77eea131aa6dd0080e3e44f682fe04d14399e8e', $result);
    }
}