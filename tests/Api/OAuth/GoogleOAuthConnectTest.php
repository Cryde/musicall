<?php

declare(strict_types=1);

namespace App\Tests\Api\OAuth;

use App\Tests\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class GoogleOAuthConnectTest extends ApiTestCase
{
    public function test_connect_uses_an_unguessable_random_state_nonce(): void
    {
        $this->client->request('GET', '/oauth/google?return_url=' . urlencode('/band-space/abc'));

        $this->assertResponseStatusCodeSame(Response::HTTP_FOUND);
        $location = (string) $this->client->getResponse()->headers->get('Location');
        $this->assertStringStartsWith('https://accounts.google.com/', $location);

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $state = is_string($query['state'] ?? null) ? $query['state'] : '';

        // State is an unguessable 64-char hex nonce, NOT a base64-encoded JSON blob
        // embedding the return URL (which made the old state attacker-derivable).
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $state);
        $this->assertStringNotContainsString('return_url', (string) base64_decode($state, true));
    }

    public function test_connect_without_return_url_still_redirects(): void
    {
        $this->client->request('GET', '/oauth/google');

        $this->assertResponseStatusCodeSame(Response::HTTP_FOUND);
        $this->assertStringStartsWith(
            'https://accounts.google.com/',
            (string) $this->client->getResponse()->headers->get('Location'),
        );
    }
}
