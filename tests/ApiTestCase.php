<?php

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ApiTestCase extends WebTestCase
{
    const HTTP_HOST = 'musicall.test';
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient([], ['HTTP_HOST' => $this->getHost()]);
    }

    protected function getHost(): string
    {
        return self::HTTP_HOST;
    }

    protected function jsonRequest()
    {

    }
}