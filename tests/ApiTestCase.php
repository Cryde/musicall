<?php

declare(strict_types=1);

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ApiTestCase extends WebTestCase
{
    const HTTP_HOST = 'musicall.test';
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        // A French browser by default. Left alone, Request::create() sends `en-us,en;q=0.5`, which would answer
        // every anonymous request in English now that `en` is enabled. A test about the locale sends its own.
        $this->client = static::createClient([], ['HTTP_HOST' => $this->getHost(), 'HTTP_ACCEPT_LANGUAGE' => 'fr']);
    }

    protected function getHost(): string
    {
        return self::HTTP_HOST;
    }
}
